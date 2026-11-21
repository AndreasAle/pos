<?php

namespace App\Services\PlatformAgents\Sales;

use App\Models\PlatformAiProspect;

/**
 * Discovery dan kualifikasi yang deterministik dan bisa dijelaskan.
 * Fakta diambil dari spreadsheet (sumber: lead) dan pesan prospek (sumber: prospek). Yang belum diketahui = UNKNOWN.
 */
final class LeadIntelligence
{
    public const UNKNOWN = 'UNKNOWN';

    public const COMPETITORS = ['moka', 'majoo', 'pawoon', 'olsera', 'esb', 'qasir', 'kasir pintar', 'kasirpintar', 'ipos', 'youtap', 'istore', 'iseller', 'loyverse', 'gobiz', 'grabmerchant'];

    /** Urutan pertanyaan berdasarkan pengaruhnya ke rekomendasi paket. */
    private const QUESTIONS = [
        'business_type' => 'usahanya jualan apa kak? kafe, resto, atau toko?',
        'outlet_count' => 'outletnya ada berapa kak?',
        'current_method' => 'sekarang catat penjualannya pakai apa kak, buku, excel, atau aplikasi lain?',
        'pain_point' => 'yang paling bikin repot sekarang apa kak, pas kasir ramai, stok, atau bikin laporan?',
        'cashiers' => 'biasanya yang pegang kasir berapa orang kak?',
        'decision_role' => 'kakak sendiri pemilik usahanya, atau ada yang ikut memutuskan?',
    ];

    /** Ambil fakta dari satu pesan. Hanya pola yang jelas; tidak menebak. */
    public function extract(string $text): array
    {
        $t = ' '.mb_strtolower($text).' ';
        $f = [];
        if (preg_match('/\b(\d{1,3})\s*(outlet|cabang|toko|gerai|store)\b/u', $t, $m) && (int) $m[1] > 0) {
            $f['outlet_count'] = (int) $m[1];
        } elseif (preg_match('/\b(satu|sebuah|1)\s*(outlet|cabang|toko|gerai)\b|\bbaru (satu|1)\b|\bcuma (satu|1)\b/u', $t)) {
            $f['outlet_count'] = 1;
        }
        foreach (self::COMPETITORS as $c) {
            if (str_contains($t, ' '.$c)) {
                $f['current_method'] = 'aplikasi '.$c;
                $f['current_pos'] = $c;
                break;
            }
        }
        if (! isset($f['current_method']) && preg_match('/\b(buku|nota|manual|tulis tangan|kertas|excel|spreadsheet|kalkulator|catat sendiri)\b/u', $t, $m)) {
            $f['current_method'] = $m[1];
        }
        if (preg_match('/\b(\d{1,2})\s*(kasir|karyawan kasir|orang (yang )?jaga kasir)\b/u', $t, $m)) {
            $f['cashiers'] = (int) $m[1];
        }
        if (preg_match('/\b(saya|aku|gue|gw)\s+(yang\s+)?(owner|pemilik|punya|yang punya)\b|\b(owner|pemilik)nya (saya|aku)\b/u', $t)) {
            $f['decision_role'] = 'pemilik';
        } elseif (preg_match('/\b(tanya|izin|diskusi|ngomong)\s+(dulu\s+)?(ke|sama|dengan)\s+(bos|owner|atasan|pemilik|suami|istri|partner)\b|\bbukan (saya|aku) yang (putus|tentu)/u', $t)) {
            $f['decision_role'] = 'bukan pengambil keputusan';
        }
        $pains = [];
        foreach (['stok' => '/\bstok|stock|bahan\b/u', 'antrean kasir' => '/\b(antri|antre|ramai|rame|numpuk)\b/u', 'laporan' => '/\b(laporan|rekap|hitung manual|tutup buku)\b/u', 'selisih uang' => '/\b(selisih|kecurian|bocor|hilang uang)\b/u', 'pesanan dapur' => '/\b(dapur|pesanan (salah|terselip|ketuker))\b/u'] as $label => $re) {
            if (preg_match($re, $t)) {
                $pains[] = $label;
            }
        }
        if ($pains) {
            $f['pain_point'] = implode(', ', $pains);
        }
        if (preg_match('/\b(segera|secepatnya|minggu ini|bulan ini|langsung mau pakai|mau buka|baru buka)\b/u', $t)) {
            $f['timeline'] = 'segera';
        } elseif (preg_match('/\b(nanti( dulu)?|bulan depan|tahun depan|belum sekarang|lihat-lihat dulu|liat2 dulu)\b/u', $t)) {
            $f['timeline'] = 'nanti';
        }

        return $f;
    }

    /** Gabungkan fakta baru ke intelligence prospek beserta sumber dan waktunya. */
    public function absorb(PlatformAiProspect $p, string $text, ?int $messageId = null): array
    {
        $intel = $p->intelligence ?? [];
        $facts = $intel['facts'] ?? [];
        foreach ($this->extract($text) as $k => $v) {
            $facts[$k] = ['value' => $v, 'source' => 'prospek', 'message_id' => $messageId, 'at' => now()->toIso8601String()];
        }
        $intel['facts'] = $facts;

        return $intel;
    }

    /** Fakta gabungan: spreadsheet + percakapan. Percakapan menang bila lebih baru. */
    public function facts(PlatformAiProspect $p): array
    {
        $out = [];
        foreach (['business_type' => $p->business_type, 'outlet_count' => $p->outlet_count, 'city' => $p->city] as $k => $v) {
            if ($v !== null && $v !== '') {
                $out[$k] = ['value' => $v, 'source' => 'lead'];
            }
        }

        return array_merge($out, $p->intelligence['facts'] ?? []);
    }

    /**
     * Kualifikasi yang bisa dijelaskan. Tiap dimensi: positive / negative / UNKNOWN + bukti.
     *
     * @return array{category:string, score:int, dimensions:array, missing:array, next_action:string}
     */
    public function qualify(PlatformAiProspect $p, array $objections = []): array
    {
        $f = $this->facts($p);
        $v = fn ($k) => $f[$k]['value'] ?? null;
        $ev = fn ($k) => isset($f[$k]) ? "{$k}={$f[$k]['value']} (sumber: {$f[$k]['source']})" : null;
        $dim = [];
        $fitTypes = ['Kafe', 'Restoran', 'Bakery', 'Minuman', 'Retail', 'Jasa'];
        $dim['business_fit'] = $v('business_type') === null ? self::UNKNOWN : (in_array($v('business_type'), $fitTypes, true) ? 'positive' : 'UNKNOWN');
        $dim['operational_need'] = $v('pain_point') ? 'positive' : ($v('current_method') && ! $v('current_pos') ? 'positive' : self::UNKNOWN);
        $dim['decision_authority'] = match ($v('decision_role')) { 'pemilik' => 'positive', 'bukan pengambil keputusan' => 'negative', default => self::UNKNOWN };
        $dim['timing'] = match ($v('timeline')) { 'segera' => 'positive', 'nanti' => 'negative', default => self::UNKNOWN };
        $dim['budget'] = in_array('PRICE_TOO_HIGH', $objections, true) ? 'negative' : self::UNKNOWN;
        $dim['engagement'] = $p->last_reply_at ? 'positive' : self::UNKNOWN;

        $score = 0;
        foreach ($dim as $d) {
            $score += $d === 'positive' ? 2 : ($d === 'negative' ? -1 : 0);
        }
        $evidence = array_values(array_filter(array_map($ev, array_keys($f))));
        $missing = array_values(array_filter(array_keys(self::QUESTIONS), fn ($k) => $v($k) === null));

        $category = match (true) {
            in_array('REQUEST_STOP', $objections, true) || $p->opted_out_at => 'opted_out',
            $dim['business_fit'] === 'positive' && $dim['operational_need'] === 'positive' && $dim['decision_authority'] !== 'negative' => 'qualified',
            $score <= -2 => 'low_fit',
            default => 'needs_discovery',
        };
        $next = match ($category) {
            'qualified' => 'Ajak coba trial dengan menghubungkan kebutuhannya ke fitur yang relevan.',
            'opted_out' => 'Jangan hubungi lagi.',
            'low_fit' => 'Jangan memaksa. Tawarkan info singkat dan biarkan dia kembali kapan saja.',
            default => $missing ? 'Tanyakan: '.self::QUESTIONS[$missing[0]] : 'Rangkum kebutuhannya lalu sarankan paket.',
        };

        return ['category' => $category, 'score' => $score, 'dimensions' => $dim, 'evidence' => $evidence, 'missing' => $missing, 'next_action' => $next, 'evaluated_at' => now()->toIso8601String()];
    }

    /** Pertanyaan discovery berikutnya: yang paling berpengaruh dan belum pernah ditanyakan dalam 3 giliran terakhir. */
    public function nextQuestion(array $qualification, array $recentlyAsked = []): ?array
    {
        foreach ($qualification['missing'] as $k) {
            if (! in_array($k, $recentlyAsked, true)) {
                return ['key' => $k, 'text' => self::QUESTIONS[$k]];
            }
        }

        return null;
    }
}
