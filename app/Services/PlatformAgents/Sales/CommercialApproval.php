<?php

namespace App\Services\PlatformAgents\Sales;

use App\Models\PlatformActivityLog;
use App\Models\PlatformAdmin;
use App\Models\PlatformAiAgent;
use App\Models\PlatformAiCommercialProposal;
use App\Models\PlatformAiProspect;
use App\Models\PlatformAiProspectEvent;
use App\Models\PlatformAiTask;
use Illuminate\Support\Facades\DB;

/**
 * Proposal harga khusus -> kartu "commercial_proposal" di Tim AI -> diputuskan super admin.
 * Proposal yang diputuskan tidak berubah; perubahan = versi baru. Keputusan memeriksa hash isi (anti basi/replay).
 */
final class CommercialApproval
{
    public function __construct(private SalesStateMachine $states) {}

    public function propose(PlatformAiProspect $prospect, array $evaluation, array $needs = []): PlatformAiCommercialProposal
    {
        return DB::transaction(function () use ($prospect, $evaluation, $needs) {
            $p = PlatformAiProspect::whereKey($prospect->id)->lockForUpdate()->firstOrFail();
            $terms = [
                'requested_price' => $evaluation['requested_price'], 'official_plan' => $evaluation['official_plan'],
                'official_price' => $evaluation['official_price'], 'discount_pct' => $evaluation['discount_pct'], 'period' => 'per bulan',
            ];
            $hash = hash('sha256', json_encode($terms));
            $pending = PlatformAiCommercialProposal::where('platform_ai_prospect_id', $p->id)->where('status', 'pending_approval')->get();
            if ($same = $pending->firstWhere('terms_hash', $hash)) {
                return $same; // permintaan sama diulang: tidak membuat proposal/kartu baru
            }
            foreach ($pending as $old) {
                $old->update(['status' => 'superseded']);
                PlatformAiTask::whereKey($old->platform_ai_task_id)->where('status', 'proposed')->update(['status' => 'rejected', 'error' => 'Digantikan proposal versi baru.']);
            }
            $version = (int) PlatformAiCommercialProposal::where('platform_ai_prospect_id', $p->id)->max('version') + 1;
            $analysis = $evaluation['analysis'] + ['prospect_needs' => $needs, 'risks' => array_values(array_filter([
                ($evaluation['analysis']['cost_status'] ?? null) === CommercialPolicy::INSUFFICIENT_DATA ? 'Data biaya belum ada: margin tidak bisa dihitung.' : null,
                'Harga khusus bisa jadi patokan bagi pelanggan lain.',
            ]))];
            $proposal = PlatformAiCommercialProposal::create([
                'platform_ai_prospect_id' => $p->id, 'version' => $version, 'status' => 'pending_approval', 'decision_state' => $evaluation['state'],
                'requested_price' => $evaluation['requested_price'], 'official_plan' => $evaluation['official_plan'], 'official_price' => $evaluation['official_price'],
                'terms' => $terms, 'analysis' => $analysis, 'terms_hash' => $hash,
            ]);
            $adit = PlatformAiAgent::where('key', 'cs')->first();
            if ($adit) {
                $fmt = fn ($v) => $v === null ? '-' : 'Rp '.number_format((float) $v, 0, ',', '.');
                $task = $adit->tasks()->create([
                    'type' => 'commercial_proposal', 'title' => "Harga khusus v{$version} · ".($p->business_name ?: '#'.$p->id),
                    'summary' => "Prospek minta {$fmt($terms['requested_price'])}/bulan. Harga resmi {$terms['official_plan']} {$fmt($terms['official_price'])}"
                        .($terms['discount_pct'] !== null ? " (diskon {$terms['discount_pct']}%)." : '.'),
                    'recommendation' => ($evaluation['state'] === CommercialPolicy::INSUFFICIENT_DATA ? 'Data biaya belum ada; putuskan manual. ' : '')
                        .'Setujui hanya bila memang mau memberi harga ini. Setelah disetujui, sampaikan sendiri ke prospek: Adit tidak mengirim penawaran otomatis.',
                    'risk' => 'high', 'requires_approval' => true, 'status' => 'proposed',
                    'payload' => ['kind' => 'commercial_proposal', 'proposal_id' => $proposal->id, 'prospect_id' => $p->id, 'version' => $version, 'terms_hash' => $hash],
                ]);
                $proposal->update(['platform_ai_task_id' => $task->id]);
            }
            PlatformAiProspectEvent::create(['platform_ai_prospect_id' => $p->id, 'type' => 'proposal', 'actor' => 'adit',
                'reason' => "Proposal harga khusus v{$version} ({$evaluation['state']})", 'data' => ['proposal_id' => $proposal->id, 'terms' => $terms]]);
            $this->states->transition($p, 'negotiation_pending_approval', 'adit', 'Permintaan harga di luar paket resmi.');

            return $proposal;
        });
    }

    /**
     * Putuskan proposal. Hanya super admin; hanya proposal yang masih menunggu; hash harus cocok dengan yang dilihat reviewer.
     *
     * @param  'approve'|'reject'|'changes'  $decision
     */
    public function decide(PlatformAiCommercialProposal $proposal, PlatformAdmin $actor, string $decision, ?string $note, string $expectedHash): array
    {
        if (! $actor->isSuperAdmin() || ! $actor->is_active) {
            return ['ok' => false, 'reason' => 'Hanya super admin yang boleh memutuskan harga khusus.'];
        }
        if (! in_array($decision, ['approve', 'reject', 'changes'], true)) {
            return ['ok' => false, 'reason' => 'Keputusan tidak dikenal.'];
        }

        return DB::transaction(function () use ($proposal, $actor, $decision, $note, $expectedHash) {
            $p = PlatformAiCommercialProposal::whereKey($proposal->id)->lockForUpdate()->first();
            if (! $p || $p->status !== 'pending_approval') {
                return ['ok' => false, 'reason' => 'Proposal sudah diputuskan atau digantikan versi baru.'];
            }
            if (! hash_equals($p->terms_hash, $expectedHash)) {
                return ['ok' => false, 'reason' => 'Isi proposal berbeda dari yang kamu lihat. Muat ulang halaman.'];
            }
            $status = ['approve' => 'approved', 'reject' => 'rejected', 'changes' => 'changes_requested'][$decision];
            $p->update(['status' => $status, 'decided_by' => $actor->id, 'decided_at' => now(), 'decision_note' => $note ? mb_substr($note, 0, 600) : null]);
            PlatformAiTask::whereKey($p->platform_ai_task_id)->where('status', 'proposed')->update([
                'status' => $decision === 'approve' ? 'completed' : 'rejected', 'approved_by' => $actor->id, 'approved_at' => now(),
                'result' => ['decision' => $status, 'terms_hash' => $p->terms_hash, 'version' => $p->version],
            ]);
            $prospect = PlatformAiProspect::find($p->platform_ai_prospect_id);
            PlatformAiProspectEvent::create(['platform_ai_prospect_id' => $p->platform_ai_prospect_id, 'type' => 'decision', 'actor' => 'admin:'.$actor->id,
                'reason' => "Proposal v{$p->version}: {$status}", 'data' => ['proposal_id' => $p->id, 'terms' => $p->terms, 'note' => $note],
                'idempotency_key' => "proposal-decision:{$p->id}"]);
            if ($prospect && $prospect->sales_stage !== 'opted_out') {
                $this->states->transition($prospect, $decision === 'approve' ? 'approved_offer_ready' : 'objection_handling', 'admin:'.$actor->id, "Proposal v{$p->version} {$status}");
            }
            PlatformActivityLog::record('ai.commercial.'.$status, "Proposal harga khusus v{$p->version} {$status}.", $p);

            return ['ok' => true, 'status' => $status];
        });
    }
}
