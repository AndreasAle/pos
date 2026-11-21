<?php

namespace App\Services\PlatformAgents\Sales;

use App\Models\PlatformAiProspect;
use App\Models\PlatformAiProspectEvent;
use Illuminate\Support\Facades\DB;

/**
 * Tahap percakapan penjualan (terpisah dari `status` kontak di spreadsheet).
 * Transisi hanya lewat tabel TRANSITIONS; opted_out tidak bisa keluar kecuali opt-in eksplisit oleh admin.
 */
final class SalesStateMachine
{
    public const STAGES = [
        'new_lead' => 'Lead baru', 'outreach_pending_approval' => 'Pesan pembuka menunggu persetujuan', 'outreach_sent' => 'Pesan pembuka terkirim',
        'engaged' => 'Membalas', 'discovery' => 'Menggali kebutuhan', 'qualified' => 'Terkualifikasi', 'objection_handling' => 'Menangani keberatan',
        'negotiation_pending_approval' => 'Harga khusus menunggu persetujuan', 'approved_offer_ready' => 'Penawaran disetujui',
        'trial_invited' => 'Diajak trial', 'trial_active' => 'Trial aktif', 'trial_conversion' => 'Trial selesai, belum bayar',
        'won' => 'Berlangganan', 'lost' => 'Tidak lanjut', 'opted_out' => 'Minta berhenti', 'human_handoff' => 'Ditangani manusia',
    ];

    private const CONVERSATION = ['engaged', 'discovery', 'qualified', 'objection_handling', 'trial_invited', 'human_handoff', 'approved_offer_ready'];

    /** from => allowed targets. '*' = dari tahap mana pun selain opted_out. */
    private const TRANSITIONS = [
        'new_lead' => ['outreach_pending_approval', 'engaged', 'trial_active', 'won'],
        'outreach_pending_approval' => ['outreach_sent', 'new_lead', 'engaged'],
        'outreach_sent' => ['engaged', 'outreach_pending_approval', 'trial_active', 'won', 'lost'],
        'engaged' => ['discovery', 'qualified', 'objection_handling', 'negotiation_pending_approval', 'trial_invited', 'human_handoff', 'trial_active', 'won', 'lost', 'outreach_pending_approval'],
        'discovery' => ['qualified', 'objection_handling', 'negotiation_pending_approval', 'trial_invited', 'human_handoff', 'trial_active', 'won', 'lost', 'outreach_pending_approval'],
        'qualified' => ['objection_handling', 'negotiation_pending_approval', 'trial_invited', 'human_handoff', 'trial_active', 'won', 'lost', 'discovery', 'outreach_pending_approval'],
        'objection_handling' => ['discovery', 'qualified', 'negotiation_pending_approval', 'trial_invited', 'human_handoff', 'trial_active', 'won', 'lost'],
        'negotiation_pending_approval' => ['approved_offer_ready', 'objection_handling', 'human_handoff', 'trial_active', 'won', 'lost'],
        'approved_offer_ready' => ['trial_invited', 'trial_active', 'won', 'lost', 'negotiation_pending_approval', 'objection_handling'],
        'trial_invited' => ['trial_active', 'discovery', 'objection_handling', 'negotiation_pending_approval', 'human_handoff', 'won', 'lost'],
        'trial_active' => ['trial_conversion', 'won', 'human_handoff', 'lost'],
        'trial_conversion' => ['won', 'objection_handling', 'negotiation_pending_approval', 'human_handoff', 'lost', 'trial_active'],
        'human_handoff' => ['engaged', 'discovery', 'qualified', 'trial_invited', 'trial_active', 'won', 'lost', 'approved_offer_ready', 'negotiation_pending_approval'],
        'won' => ['trial_conversion'], // langganan habis tanpa perpanjangan
        'lost' => ['engaged', 'trial_active', 'won'],
        'opted_out' => [],
    ];

    public static function can(string $from, string $to): bool
    {
        if ($from === 'opted_out') {
            return false;
        }

        return $to === 'opted_out' || in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function isConversation(string $stage): bool
    {
        return in_array($stage, self::CONVERSATION, true);
    }

    /**
     * Pindahkan tahap secara atomik. Mengembalikan false bila transisi tidak sah, sudah di tahap itu, atau kunci idempotensi sudah dipakai.
     * $expectedVersion (opsional) menolak pembaruan yang basi.
     */
    public function transition(PlatformAiProspect $prospect, string $to, string $actor, string $reason, array $data = [], ?string $key = null, ?int $expectedVersion = null): bool
    {
        abort_unless(array_key_exists($to, self::STAGES), 500, 'Tahap tidak dikenal.');

        return DB::transaction(function () use ($prospect, $to, $actor, $reason, $data, $key, $expectedVersion) {
            $p = PlatformAiProspect::whereKey($prospect->id)->lockForUpdate()->firstOrFail();
            if ($key && PlatformAiProspectEvent::where('idempotency_key', $key)->exists()) {
                return false;
            }
            if ($expectedVersion !== null && $p->stage_version !== $expectedVersion) {
                return false;
            }
            $from = $p->sales_stage ?: 'new_lead';
            if ($from === $to || ! self::can($from, $to)) {
                if ($from !== $to) {
                    PlatformAiProspectEvent::create(['platform_ai_prospect_id' => $p->id, 'type' => 'policy_denial', 'from_stage' => $from, 'to_stage' => $to,
                        'actor' => $actor, 'reason' => 'Transisi tidak sah: '.$reason]);
                }

                return false;
            }
            $p->forceFill(['sales_stage' => $to, 'stage_version' => $p->stage_version + 1])->save();
            PlatformAiProspectEvent::create(['platform_ai_prospect_id' => $p->id, 'type' => 'transition', 'from_stage' => $from, 'to_stage' => $to,
                'actor' => $actor, 'reason' => mb_substr($reason, 0, 255), 'data' => $data ?: null, 'idempotency_key' => $key]);
            $prospect->setRawAttributes($p->getAttributes(), true);

            return true;
        });
    }

    /** Opt-in ulang hanya oleh admin yang terautentikasi, dengan alasan tertulis. */
    public function optIn(PlatformAiProspect $prospect, int $adminId, string $reason): bool
    {
        if (trim($reason) === '' || $prospect->sales_stage !== 'opted_out') {
            return false;
        }

        return DB::transaction(function () use ($prospect, $adminId, $reason) {
            $p = PlatformAiProspect::whereKey($prospect->id)->lockForUpdate()->firstOrFail();
            $p->forceFill(['sales_stage' => 'engaged', 'stage_version' => $p->stage_version + 1, 'opted_out_at' => null, 'status' => 'membalas'])->save();
            PlatformAiProspectEvent::create(['platform_ai_prospect_id' => $p->id, 'type' => 'transition', 'from_stage' => 'opted_out', 'to_stage' => 'engaged',
                'actor' => 'admin:'.$adminId, 'reason' => 'Opt-in ulang: '.mb_substr($reason, 0, 200)]);
            $prospect->setRawAttributes($p->getAttributes(), true);

            return true;
        });
    }
}
