<?php

namespace App\Services\PlatformAgents\Sales;

use App\Http\Controllers\GuideController;
use App\Models\PlatformAiProspect;

/**
 * Pemeriksa balasan untuk prospek sebelum dikirim. Deterministik, tanpa panggilan model tambahan.
 * Gagal keras: klaim layanan/jaminan tak terverifikasi, URL yokapos.com yang tidak ada -> kalimatnya dibuang.
 * Gagal lunak: terlalu banyak pertanyaan, link trial tidak pada tempatnya, nama bisnis dipakai sebagai nama orang,
 * frasa basa-basi -> dirapikan. Semua temuan dikembalikan untuk metrik.
 */
final class ResponseQuality
{
    private const SUPPORT_CLAIM = '/\b(24 ?jam|24\/7|nonstop|setiap saat|kapan (saja|pun) (dibantu|ada)|tim (support |cs )?(indonesia|lokal) (langsung|siap)|langsung dari (kami|tim)|respon(s)? (cepat|instan|kilat)|fast respon|pasti (dibantu|dibalas|beres|aman)|dijamin|garansi|tidak akan (error|hilang)|nggak (bakal|akan) (error|hilang)|100 ?%|tanpa biaya tambahan|gratis (update|perbaikan)|update (gratis|berkala)|perbaikan bug berkala)\b/iu';

    private const FILLER = ['/\bpaham banget( sih)?,?\s*/iu', '/\btenang aja,?\s*/iu', '/\bpastinya,?\s*/iu', '/^wah,?\s*/iu'];

    /** Buang potongan menggantung (diakhiri kata sambung/koma) dan rapikan huruf awal. */
    private function coherent(string $text): string
    {
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        do {
            $prev = $text;
            $text = preg_replace('/\s*(,|:|;|\bdan|\batau|\byang|\buntuk|\bseperti|\bjadi|\bkarena)\s*$/iu', '', trim($text));
        } while ($text !== $prev);
        if ($text !== '' && ! preg_match('/[.!?)]$/u', $text)) {
            $text .= '.';
        }
        if ($text !== '') {
            $text = mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
        }

        return $text === '.' ? '' : $text;
    }

    private function alreadyAsked(string $question, array $previous): bool
    {
        $norm = fn ($s) => preg_replace('/[^a-z ]/', '', mb_strtolower($s));
        $q = $norm($question);
        foreach ($previous as $prev) {
            foreach (preg_split('/(?<=[.!?])\s+/u', (string) $prev) ?: [] as $s) {
                if (str_contains($s, '?')) {
                    similar_text($q, $norm($s), $pct);
                    if ($pct >= 80) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /** Balasan cadangan dari data terverifikasi, per tujuan. null = lebih baik tidak membalas (manusia menindaklanjuti). */
    public function fallback(array $strategy, PlatformAiProspect $p): ?string
    {
        $plans = app(CommercialPolicy::class)->officialPlans()
            ->map(fn ($pl) => "{$pl->name} Rp ".number_format((float) $pl->price, 0, ',', '.')."/bulan ({$pl->max_outlets} outlet, {$pl->max_users} user)")->implode(', ');

        return match ($strategy['objective'] ?? null) {
            'ANSWER_DIRECT_QUESTION' => $plans !== '' ? "Paket Yoka POS saat ini: {$plans}. Ada juga trial gratis 7 hari." : null,
            'IDENTIFY_HARDWARE_INTEREST' => 'Langganan Yoka POS belum termasuk printer. Struk bisa dicetak lewat print browser atau printer Bluetooth dengan aplikasi RawBT di Android; sebaiknya cek dulu tipe printernya.',
            'CLOSE_CONVERSATION' => ($strategy['closing'] ?? null) === 'OPTED_OUT' ? 'Baik, kami tidak akan menghubungi lagi. Terima kasih.' : 'Terima kasih. Kalau nanti mau lanjut, tinggal balas pesan ini ya.',
            'BUILD_TRUST' => 'Boleh diceritakan, kendala waktu itu lebih ke aplikasinya error, data hilang, atau bantuannya sulit dihubungi?',
            'READY_TO_BUY' => ConversationStrategy::BUY_STEPS,
            'OFFER_TRIAL' => 'Bisa, daftar di yokapos.com/register untuk trial gratis 7 hari.',
            'REQUEST_HUMAN_APPROVAL', 'ARRANGE_FIELD_VISIT' => 'Baik, saya teruskan ke tim Yoka dulu ya.',
            default => null,
        };
    }

    public function verifiedUrls(): array
    {
        $paths = ['', 'register', 'panduan'];
        foreach (array_keys(GuideController::guides()) as $slug) {
            $paths[] = 'panduan/'.$slug;
        }

        return $paths;
    }

    /** @return array{text:string, issues:array<int,string>} */
    /** @param array<int,string> $previous pesan keluar sebelumnya ke nomor ini */
    public function review(string $text, PlatformAiProspect $p, array $strategy, array $previous = []): array
    {
        $issues = [];
        $original = $text;
        $urls = $this->verifiedUrls();
        // Nama bisnis dipakai sebagai nama orang ("kak Andopos").
        if ($p->business_name !== '' && ! OpeningStrategy::personName($p)) {
            foreach (array_filter([$p->business_name, $p->contact_name]) as $n) {
                $re = '/\b(kak|kakak|pak|bu|mas|mbak)\s+'.preg_quote($n, '/').'\b/iu';
                if (preg_match($re, $text)) {
                    $text = preg_replace($re, '$1', $text);
                    $issues[] = 'business_name_as_person';
                }
            }
        }
        foreach (self::FILLER as $re) {
            if (preg_match($re, $text)) {
                $text = preg_replace($re, '', $text);
                $issues[] = 'filler';
            }
        }

        $paragraphs = preg_split("/\n\s*\n/", trim($text)) ?: [];
        $out = [];
        $questions = 0;
        foreach ($paragraphs as $para) {
            $kept = [];
            foreach (preg_split('/(?<=[.!?])\s+/u', trim($para)) ?: [] as $s) {
                $s = trim($s);
                if ($s === '') {
                    continue;
                }
                if (preg_match(self::SUPPORT_CLAIM, $s) && ! SupportPolicy::mentionsOfficialHours($s)) {
                    $issues[] = 'unverified_support_claim';

                    continue;
                }
                if (preg_match_all('/yokapos\.com\/?([a-z0-9\-\/]*)/iu', $s, $mm)) {
                    $bad = array_filter($mm[1], fn ($path) => ! in_array(trim(mb_strtolower($path), '/'), $urls, true));
                    if ($bad) {
                        $issues[] = 'unverified_url';

                        continue;
                    }
                    if (! $strategy['allow_trial_cta'] && preg_match('/yokapos\.com\/register|coba gratis|trial/iu', $s) && ! preg_match('/panduan/iu', $s)) {
                        $issues[] = 'trial_cta_out_of_place';

                        continue;
                    }
                }
                if (($strategy['objective'] ?? null) === 'READY_TO_BUY' && preg_match('/\b(tanya|tanyakan|teruskan|terusin|sampaikan|infokan)\b.{0,25}\btim\b/iu', $s)) {
                    $issues[] = 'handoff_on_buy';

                    continue;
                }
                if (str_contains($s, '?')) {
                    if ($this->alreadyAsked($s, $previous)) {
                        $issues[] = 'repeated_question';

                        continue;
                    }
                    if (! $strategy['allow_question'] || $questions >= 1) {
                        $issues[] = 'extra_question';

                        continue;
                    }
                    $questions++;
                }
                $kept[] = $s;
            }
            if ($kept) {
                $out[] = implode(' ', $kept);
            }
        }
        $text = $this->coherent(trim(implode("\n\n", $out)));
        if (($strategy['objective'] ?? null) === 'READY_TO_BUY' && ! str_contains($text, 'yokapos.com/register')) {
            $text = trim($text."\n\n".ConversationStrategy::BUY_STEPS);
            $issues[] = 'buy_steps_added';
        }
        // Pertanyaan harga harus tetap terjawab; balasan kosong/rusak diganti fallback terverifikasi.
        $priceLost = ($strategy['objective'] ?? null) === 'ANSWER_DIRECT_QUESTION' && preg_match('/rp\.?\s?\d/iu', $original) && ! preg_match('/rp\.?\s?\d/iu', $text);
        if ($text === '' || $priceLost) {
            $fallback = $this->fallback($strategy, $p);
            $issues[] = $fallback !== null ? 'fallback_used' : 'no_safe_reply';
            $text = $fallback ?? '';
        }
        if (mb_strlen($text) > 600) {
            $issues[] = 'too_long';
        }
        if (substr_count(mb_strtolower($text), 'kak') > 2) {
            $issues[] = 'kak_overuse';
        }

        return ['text' => $text, 'issues' => array_values(array_unique($issues))];
    }
}
