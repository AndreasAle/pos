<?php

namespace App\Services\PlatformAgents;

use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\PlatformAiAgent;
use App\Models\PlatformAiProspect;
use App\Models\PlatformAiTask;
use App\Models\PlatformAiWhatsAppMessage;
use App\Services\PlatformWhatsApp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Prospek Adit. Batas keras ada di kode, bukan di prompt:
 *  - maksimal DAILY_LIMIT draf pesan pembuka/follow-up per hari (bukan pesan massal),
 *  - satu prospek maksimal MAX_CONTACTS kali dihubungi (pembuka + satu follow-up setelah FOLLOWUP_AFTER_DAYS hari tanpa balasan),
 *  - pesan hanya terkirim setelah manusia menekan "Setujui & kirim",
 *  - nomor yang minta berhenti, sudah jadi pelanggan, dobel, atau tanpa sumber tidak pernah dihubungi.
 */
class ProspectDesk
{
    public const DAILY_LIMIT = 3;

    public const MAX_CONTACTS = 2;

    public const FOLLOWUP_AFTER_DAYS = 3;

    public const STOP_PATTERN = '/\b(stop|berhenti|jangan\s+(hubungi|chat|kirim|wa)|unsubscribe|ga(k|ak)?\s+minat|tidak\s+minat|nggak\s+minat|ngga\s+minat|jangan\s+ganggu|hapus\s+nomor)\b/i';

    private const TYPES = [
        'Kafe' => ['kafe', 'cafe', 'coffee', 'kopi', 'kedai kopi', 'coffee shop'],
        'Restoran' => ['resto', 'restoran', 'restaurant', 'rumah makan', 'warung makan', 'rm ', 'warteg', 'bakso', 'mie', 'ayam', 'seafood', 'catering'],
        'Bakery' => ['bakery', 'roti', 'kue', 'cake', 'pastry'],
        'Minuman' => ['boba', 'juice', 'jus', 'es teh', 'minuman', 'thai tea'],
        'Retail' => ['toko', 'retail', 'minimarket', 'kelontong', 'grosir', 'fashion', 'butik', 'distro', 'apotek'],
        'Jasa' => ['laundry', 'salon', 'barbershop', 'barber', 'spa', 'bengkel', 'cuci', 'studio', 'klinik'],
    ];

    public static function isTestNumber(?string $phone): bool
    {
        return $phone !== null && $phone !== '' && in_array($phone, (array) config('yoka_sales.test_numbers', []), true);
    }

    /** Rapikan isian dan periksa apakah prospek aman dihubungi. Tidak mengirim apa pun. */
    public function check(PlatformAiProspect $p): PlatformAiProspect
    {
        $issues = [];
        foreach (['business_name', 'contact_name', 'phone', 'business_type', 'city', 'source', 'notes'] as $f) {
            $p->{$f} = (string) ($p->{$f} ?? ''); // baris baru dari "Tambah baris" masih kosong
        }
        $p->status ??= 'baru';
        $p->business_name = Str::of($p->business_name)->squish()->limit(150, '')->toString();
        $p->contact_name = Str::of($p->contact_name)->squish()->title()->limit(100, '')->toString();
        $p->city = Str::of($p->city)->squish()->title()->limit(80, '')->toString();
        $p->source = Str::of($p->source)->squish()->limit(120, '')->toString();
        $p->business_type = $this->normalizeType($p->business_type, $p->business_name);
        $p->phone_normalized = PlatformWhatsApp::normalize((string) $p->phone);

        if (! $p->phone_normalized) {
            $issues[] = ['level' => 'blok', 'text' => 'Nomor WA tidak valid (pakai format 08xx atau 62xx).'];
        } else {
            $dup = PlatformAiProspect::where('phone_normalized', $p->phone_normalized)->when($p->exists, fn ($q) => $q->whereKeyNot($p->id))->first();
            if ($dup) {
                $issues[] = ['level' => 'blok', 'text' => "Nomor sama dengan prospek #{$dup->id} ({$dup->business_name})."];
            }
            if (self::isTestNumber($p->phone_normalized)) {
                $issues[] = ['level' => 'info', 'text' => 'Nomor uji internal: boleh dihubungi walau terdaftar sebagai toko.'];
            } elseif ($business = $this->businessFor($p->phone_normalized)) {
                $p->matched_business_id = $business->id;
                $issues[] = ['level' => 'blok', 'text' => "Sudah terdaftar di Yoka sebagai \"{$business->name}\". Bukan prospek baru."];
            }
            if (PlatformAiWhatsAppMessage::where('phone', $p->phone_normalized)->exists()) {
                $issues[] = ['level' => 'info', 'text' => 'Nomor ini pernah chat dengan Yoka. Adit membaca riwayatnya sebelum menghubungi.'];
            }
        }
        if ($p->business_name === '') {
            $issues[] = ['level' => 'blok', 'text' => 'Nama bisnis kosong.'];
        }
        if ($p->source === '') {
            $issues[] = ['level' => 'blok', 'text' => 'Isi sumber nomor (mis. kenalan, pameran, minta brosur). Nomor tanpa sumber tidak dihubungi.'];
        }
        if ($p->business_type === '') {
            $issues[] = ['level' => 'saran', 'text' => 'Jenis usaha kosong: pesan pembuka jadi kurang personal.'];
        }
        if ($p->city === '') {
            $issues[] = ['level' => 'saran', 'text' => 'Kota kosong.'];
        }
        if ($p->opted_out_at) {
            $issues[] = ['level' => 'blok', 'text' => 'Prospek minta berhenti dihubungi.'];
        }

        $p->check_issues = $issues;
        $p->checked_at = now();
        if (in_array($p->status, ['baru', 'perlu_dicek', 'siap'], true)) {
            $p->status = collect($issues)->contains('level', 'blok') ? 'perlu_dicek' : 'siap';
        }
        $p->save();

        return $p;
    }

    /**
     * Susun draf hari ini (maks DAILY_LIMIT): follow-up yang jatuh tempo dulu, lalu prospek siap yang paling lama menunggu.
     *
     * @return array<int,array{prospect:string,kind:string}>
     */
    public function draftToday(PlatformAiAgent $adit): array
    {
        return DB::transaction(function () use ($adit) {
            $used = $this->countedDraftsToday()->lockForUpdate()->count();
            $slots = self::DAILY_LIMIT - $used;
            $made = [];
            if ($slots <= 0) {
                return $made;
            }

            $followups = PlatformAiProspect::where('status', 'dihubungi')->where('contact_count', '<', self::MAX_CONTACTS)
                ->whereNull('opted_out_at')->where('last_contacted_at', '<=', now()->subDays(self::FOLLOWUP_AFTER_DAYS))
                ->orderBy('last_contacted_at')->limit($slots)->get();
            $tests = (array) config('yoka_sales.test_numbers', []);
            foreach ($followups as $p) {
                $made[] = $this->createDraft($adit, $p, 'prospect_followup');
                $slots -= in_array($p->phone_normalized, $tests, true) ? 0 : 1;
            }

            if ($slots > 0) {
                foreach (PlatformAiProspect::where('status', 'siap')->whereNull('opted_out_at')->orderBy('id')->limit($slots * 3)->get() as $p) {
                    if ($slots <= 0) {
                        break;
                    }
                    if ($this->check($p)->status !== 'siap') {
                        continue; // data berubah sejak dicek: jangan dihubungi
                    }
                    $made[] = $this->createDraft($adit, $p, 'prospect_opening');
                    $slots -= in_array($p->phone_normalized, $tests, true) ? 0 : 1; // nomor uji tidak memakai jatah
                }
            }

            return $made;
        });
    }

    /**
     * Draf prospek hari ini yang dihitung ke batas harian. Draf ke nomor uji internal (ADIT_TEST_NUMBERS,
     * nomor milik tim Yoka sendiri) tidak dihitung, supaya tes tidak menghabiskan jatah prospek sungguhan.
     */
    private function countedDraftsToday()
    {
        $q = PlatformAiTask::where('type', 'whatsapp_draft')->whereDate('created_at', today())
            ->whereIn('payload->kind', ['prospect_opening', 'prospect_followup']);
        $tests = (array) config('yoka_sales.test_numbers', []);

        return $tests ? $q->where(fn ($w) => $w->whereNull('payload->phone')->orWhereNotIn('payload->phone', $tests)) : $q;
    }

    private function createDraft(PlatformAiAgent $adit, PlatformAiProspect $p, string $kind): array
    {
        $opening = app(Sales\OpeningStrategy::class);
        $message = config('yoka_sales.bd21.opening')
            ? ($kind === 'prospect_followup' ? $opening->followup($p) : $opening->opening($p)['message'])
            : ($kind === 'prospect_followup' ? $this->followupMessage($p) : $this->openingMessage($p));
        $task = $adit->tasks()->create([
            'type' => 'whatsapp_draft',
            'title' => ($kind === 'prospect_followup' ? 'Follow-up prospek · ' : 'Prospek baru · ').$p->business_name,
            'summary' => $message,
            'recommendation' => trim(collect([$p->business_type, $p->city, $p->outlet_count ? $p->outlet_count.' outlet' : null, 'sumber: '.$p->source, $p->notes ?: null])->filter()->implode(' · ')),
            'risk' => 'medium', 'requires_approval' => true, 'status' => 'proposed',
            'payload' => ['kind' => $kind, 'prospect_id' => $p->id, 'phone' => $p->phone_normalized, 'channel' => 'whatsapp'],
        ]);
        $p->update(['status' => 'dijadwalkan', 'last_task_id' => $task->id]);
        $this->stage($p, 'outreach_pending_approval', 'adit', 'Draf '.$kind.' disusun.');

        return ['prospect' => $p->business_name, 'kind' => $kind];
    }

    /** Dipanggil setelah "Setujui & kirim" berhasil. */
    public function markSent(PlatformAiTask $task): void
    {
        $p = PlatformAiProspect::find(data_get($task->payload, 'prospect_id'));
        if (! $p) {
            return;
        }
        $p->update(['status' => in_array($p->status, ['membalas', 'tertarik'], true) ? $p->status : 'dihubungi', 'contact_count' => $p->contact_count + 1, 'last_contacted_at' => now()]);
        $this->stage($p, 'outreach_sent', 'admin:'.($task->approved_by ?? '?'), 'Disetujui & dikirim.', "sent:{$task->id}");
    }

    /** Draf ditolak: prospek kembali ke antrean (atau tetap 'dihubungi' bila sudah pernah dihubungi). */
    public function markRejected(PlatformAiTask $task): void
    {
        $p = PlatformAiProspect::find(data_get($task->payload, 'prospect_id'));
        if ($p && $p->status === 'dijadwalkan') {
            $p->update(['status' => $p->contact_count > 0 ? 'dihubungi' : 'siap']);
            $this->stage($p, $p->contact_count > 0 ? 'outreach_sent' : 'new_lead', 'admin', 'Draf ditolak.');
        }
    }

    /** Pesan masuk dari nomor prospek: catat balasan, hormati permintaan berhenti. Mengembalikan prospek bila ada. */
    public function inbound(string $phone, string $body): ?PlatformAiProspect
    {
        $p = PlatformAiProspect::where('phone_normalized', $phone)->first();
        if (! $p) {
            return null;
        }
        $changes = ['last_reply_at' => now()];
        if (preg_match(self::STOP_PATTERN, $body)) {
            $changes += ['status' => 'berhenti', 'opted_out_at' => now()];
            // Draf yang belum dikirim untuk nomor ini dibatalkan.
            PlatformAiTask::where('type', 'whatsapp_draft')->where('status', 'proposed')->where('payload->prospect_id', $p->id)
                ->update(['status' => 'rejected', 'error' => 'Prospek minta berhenti dihubungi.']);
        } elseif (in_array($p->status, ['dihubungi', 'dijadwalkan', 'siap', 'baru', 'perlu_dicek'], true)) {
            $changes['status'] = 'membalas';
        }
        $p->update($changes);
        if ($p->opted_out_at) {
            $this->stage($p, 'opted_out', 'prospek', 'Prospek minta berhenti dihubungi.');
        }

        return $p;
    }

    /** Konteks untuk Adit saat prospek membalas. */
    public function context(PlatformAiProspect $p): string
    {
        $bits = collect([
            $p->business_name ? "bisnis \"{$p->business_name}\"" : null,
            $p->business_type ?: null, $p->city ?: null,
            $p->outlet_count ? "{$p->outlet_count} outlet" : null,
            ($who = Sales\OpeningStrategy::personName($p)) ? "nama kontak {$who}" : 'nama orangnya belum diketahui (JANGAN sapa dengan nama bisnis; cukup "kak" atau "Pak/Bu")',
            $p->notes ? "catatan tim: {$p->notes}" : null,
        ])->filter()->implode(', ');

        return "Pengirim adalah PROSPEK yang kita hubungi duluan ({$bits}). Kamu sales yang ramah, bukan penjual yang memaksa. "
            .'Urutan: jawab dulu yang dia tanya; gali kebutuhan dengan SATU pertanyaan per pesan (berapa outlet, jualan apa, sekarang catat penjualan pakai apa); '
            .'sarankan paket dari PAKET AKTIF yang cocok dengan alasannya; ajak coba gratis dulu lewat yokapos.com/register. '
            .'Tidak ada diskon atau harga khusus: kalau dia minta, minta bertemu, atau mau paket Custom, isi "needs_human": true dan bilang kamu tanyakan ke tim. '
            .'Kalau dia bilang tidak minat atau minta berhenti, terima dengan sopan dalam satu pesan pendek dan jangan menawarkan lagi.';
    }

    /** Status otomatis dari data: sudah daftar trial atau sudah bayar. */
    public function refreshConversions(): int
    {
        $n = 0;
        foreach (PlatformAiProspect::whereNotNull('phone_normalized')->whereNotIn('status', ['bayar', 'berhenti'])->where('contact_count', '>', 0)->get() as $p) {
            $business = $this->businessFor($p->phone_normalized);
            if (! $business) {
                continue;
            }
            $paid = BusinessSubscription::where('business_id', $business->id)->whereNotNull('paid_at')->where('amount', '>', 0)->exists();
            $status = $paid ? 'bayar' : 'trial';
            if ($p->status !== $status) {
                $p->update(['status' => $status, 'matched_business_id' => $business->id]);
                app(Sales\SalesOrchestrator::class)->syncTrial($p);
                $n++;
            }
        }

        return $n;
    }

    public function stats(): array
    {
        $by = PlatformAiProspect::query()->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status')->all();
        $contacted = PlatformAiProspect::where('contact_count', '>', 0)->count();
        $replied = PlatformAiProspect::whereNotNull('last_reply_at')->where('contact_count', '>', 0)->count();

        return [
            'total' => array_sum($by), 'by_status' => $by, 'contacted' => $contacted, 'replied' => $replied,
            'trial' => ($by['trial'] ?? 0) + ($by['bayar'] ?? 0), 'paid' => $by['bayar'] ?? 0,
            'drafts_today' => $this->countedDraftsToday()->count(),
            'daily_limit' => self::DAILY_LIMIT,
        ];
    }

    private function openingMessage(PlatformAiProspect $p): string
    {
        $hi = $p->contact_name ? "Halo kak {$p->contact_name}" : 'Halo kak';
        $where = $p->city ? " di {$p->city}" : '';
        $hook = match ($p->business_type) {
            'Kafe', 'Minuman' => 'banyak kedai kopi dan minuman yang ribet pas jam ramai: pesanan numpuk, stok bahan susah dipantau, laporan harus dihitung manual.',
            'Restoran' => 'banyak resto yang repot pas jam makan: pesanan ke dapur sering terselip, stok bahan susah dipantau, laporan harian masih manual.',
            'Bakery' => 'banyak toko roti dan kue yang susah pantau stok dan produk terlaris tiap hari.',
            'Retail' => 'banyak toko yang stoknya sering selisih dan laporan penjualannya masih dihitung manual.',
            'Jasa' => 'banyak usaha jasa yang catatan transaksi dan pelanggannya masih di buku.',
            default => 'banyak usaha yang pencatatan penjualan dan stoknya masih manual.',
        };
        $outlet = $p->outlet_count && $p->outlet_count > 1 ? " Dengan {$p->outlet_count} outlet, semua laporan bisa dilihat dari satu tempat." : '';

        return "{$hi}, saya Adit dari Yoka POS, aplikasi kasir untuk usaha seperti {$p->business_name}{$where}. "
            ."Kami lihat {$hook}{$outlet}\n\n"
            .'Kalau kakak berkenan, bisa coba gratis 7 hari di yokapos.com/register, tanpa kartu kredit. Boleh saya tanya, sekarang pencatatan penjualannya pakai apa kak?';
    }

    private function followupMessage(PlatformAiProspect $p): string
    {
        $hi = $p->contact_name ? "Halo kak {$p->contact_name}" : 'Halo kak';

        return "{$hi}, Adit dari Yoka POS lagi. Mau pastikan pesan kemarin sampai. "
            .'Kalau sekarang belum butuh aplikasi kasir, tidak apa-apa kak, saya tidak akan mengganggu lagi. '
            .'Kalau penasaran, coba gratis 7 hari di yokapos.com/register.';
    }

    /** Tahap penjualan (Adit BD 2.0) ikut bergerak; transisi tidak sah diabaikan dan tercatat. */
    private function stage(PlatformAiProspect $p, string $to, string $actor, string $reason, ?string $key = null): void
    {
        app(Sales\SalesStateMachine::class)->transition($p, $to, $actor, $reason, [], $key);
    }

    private function normalizeType(string $type, string $name): string
    {
        $hay = ' '.mb_strtolower(trim($type.' '.$name)).' ';
        foreach (self::TYPES as $label => $words) {
            if (mb_strtolower(trim($type)) === mb_strtolower($label)) {
                return $label;
            }
        }
        foreach (self::TYPES as $label => $words) {
            foreach ($words as $w) {
                if (str_contains($hay, ' '.trim($w)) || str_contains($hay, trim($w).' ')) {
                    return $label;
                }
            }
        }

        return Str::of($type)->squish()->title()->limit(40, '')->toString();
    }

    private function businessFor(string $phone): ?Business
    {
        $variants = [$phone, '0'.substr($phone, 2), '+'.$phone, substr($phone, 2)];

        return Business::whereIn('phone', $variants)->first()
            ?? Business::whereHas('owner', fn ($q) => $q->whereIn('phone', $variants))->first();
    }
}
