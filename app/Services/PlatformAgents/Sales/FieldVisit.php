<?php

namespace App\Services\PlatformAgents\Sales;

use App\Models\PlatformAiAgent;
use App\Models\PlatformAiProspect;
use App\Models\PlatformAiProspectEvent;
use App\Models\PlatformAiTask;

/**
 * Founder-led field sales: prospek yang SECARA EKSPLISIT setuju didatangi / minta demo langsung / mau lihat perangkat
 * -> tahap ready_for_field_visit + kartu "field_visit_brief" untuk founder. Tidak menjadwalkan apa pun sendiri.
 */
final class FieldVisit
{
    public const CONSENT = '/\b(boleh|bisa|silakan|silahkan|mau|oke|ok|siap)\b.{0,30}\b(datang|mampir|ke (warung|toko|tempat|lokasi)( saya| aku)?|ketemu(an)? langsung|demo (langsung|di tempat)|lihat langsung|tunjukin langsung|dicontohin langsung)\b|\b(datang|mampir) (aja|saja) (ke|kesini|ke sini)\b|\b(kapan|bisa) (datang|mampir|demo) (ke|kesini|ke sini)\b|\b(mau|pengen|ingin|boleh) (lihat|liat|coba) (printer|alat|perangkat)(nya)? langsung\b/iu';

    public function __construct(private SalesStateMachine $states, private LeadIntelligence $intel) {}

    public static function detects(string $text): bool
    {
        return (bool) preg_match(self::CONSENT, $text);
    }

    /** Buat/perbarui brief kunjungan. Satu kartu terbuka per prospek. */
    public function prepare(PlatformAiProspect $p, string $evidence, ?int $messageId = null): ?PlatformAiTask
    {
        if ($p->opted_out_at || $p->sales_stage === 'opted_out') {
            return null;
        }
        $this->states->transition($p, 'ready_for_field_visit', 'prospek', 'Prospek setuju didatangi / minta demo langsung.', ['evidence' => mb_substr($evidence, 0, 200)]);
        $brief = $this->brief($p->fresh(), $evidence);
        $open = PlatformAiTask::where('type', 'field_visit_brief')->whereIn('status', ['proposed', 'approved'])->where('payload->prospect_id', $p->id)->first();
        if ($open) {
            $open->update(['summary' => $brief['summary'], 'payload' => array_merge((array) $open->payload, ['brief' => $brief['data']])]);

            return $open;
        }
        $adit = PlatformAiAgent::where('key', 'cs')->first();
        if (! $adit) {
            return null;
        }
        $task = $adit->tasks()->create([
            'type' => 'field_visit_brief', 'title' => 'Siap dikunjungi · '.($p->business_name ?: '#'.$p->id).($p->city ? " ({$p->city})" : ''),
            'summary' => $brief['summary'],
            'recommendation' => 'Hubungi prospek untuk sepakati waktu sendiri. Adit tidak menjanjikan jam atau tanggal.',
            'risk' => 'low', 'requires_approval' => false, 'status' => 'proposed',
            'payload' => ['kind' => 'field_visit_brief', 'prospect_id' => $p->id, 'phone' => $p->phone_normalized, 'brief' => $brief['data']],
        ]);
        try {
            app(\App\Services\PlatformTelegramNotifications::class)->fieldVisitAlert($task);
        } catch (\Throwable $e) {
            report($e); // kabar Telegram gagal tidak boleh menggagalkan balasan ke prospek
        }
        PlatformAiProspectEvent::create(['platform_ai_prospect_id' => $p->id, 'type' => 'field_visit', 'actor' => 'adit', 'reason' => 'Brief kunjungan dibuat.',
            'data' => ['task_id' => $task->id, 'consent' => mb_substr($evidence, 0, 200), 'message_id' => $messageId]]);

        return $task;
    }

    /** @return array{summary:string, data:array} */
    public function brief(PlatformAiProspect $p, string $consent): array
    {
        $facts = collect($this->intel->facts($p))->map(fn ($f) => $f['value']);
        $i = $p->intelligence ?? [];
        $objections = collect($i['objections'] ?? [])->pluck('type')->unique()->values()->all();
        $hw = $i['hardware'] ?? null;
        $pains = (string) ($facts['pain_point'] ?? '');
        $demo = array_values(array_filter([
            str_contains($pains, 'antrean') ? 'Alur kasir cepat (login PIN) dan QR Meja' : null,
            str_contains($pains, 'laporan') || str_contains($pains, 'margin') ? 'Laporan harian, estimasi profit, laba rugi' : null,
            str_contains($pains, 'stok') ? 'Stok bahan baku dan resep' : null,
            $hw ? 'Tes cetak struk dengan printer prospek (cek tipe dulu)' : null,
            'Daftar trial bersama di tempat (yokapos.com/register)',
        ]));
        $data = [
            'bisnis' => $p->business_name, 'jenis' => $p->business_type, 'kota' => $p->city, 'outlet' => $p->outlet_count,
            'kontak' => OpeningStrategy::personName($p), 'cara_catat_sekarang' => $facts['current_method'] ?? 'belum diketahui',
            'masalah' => $pains ?: 'belum diketahui', 'keberatan' => $objections, 'minat_perangkat' => $hw,
            'tanya_harga' => (bool) collect($i['objections'] ?? [])->firstWhere('type', 'PRICE_TOO_HIGH') || ! empty($i['commercial']),
            'persetujuan_kunjungan' => mb_substr($consent, 0, 200), 'demo_disarankan' => $demo,
            'belum_diketahui' => $i['qualification']['missing'] ?? [],
        ];
        $summary = "Prospek setuju didatangi: \"".mb_substr($consent, 0, 120)."\". Cara catat: {$data['cara_catat_sekarang']}. Masalah: {$data['masalah']}."
            .($objections ? ' Keberatan: '.implode(', ', $objections).'.' : '').($hw ? ' Minat printer.' : '').' Demo: '.implode('; ', $demo).'.';

        return ['summary' => $summary, 'data' => $data];
    }
}
