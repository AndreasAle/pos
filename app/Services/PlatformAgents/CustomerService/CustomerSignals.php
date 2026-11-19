<?php

namespace App\Services\PlatformAgents\CustomerService;

/**
 * Sinyal percakapan pelanggan WhatsApp, dihitung deterministik (tanpa model, tanpa biaya).
 *
 * Dipakai untuk dua hal:
 *  1. memilih panduan ServicePlaybook dan pengetahuan produk yang relevan, supaya prompt tetap kecil;
 *  2. keputusan yang tidak boleh diserahkan ke model: eskalasi ke manusia (minta orang langsung,
 *     keluhan berulang) dan klaim yang boleh diucapkan.
 *
 * Sinyal emosi hanya membaca kata yang memang ditulis pelanggan. Bukan diagnosis perasaan.
 */
final class CustomerSignals
{
    public const HUMAN = 'human_request';
    public const COMPLAINT = 'complaint';
    public const ANGER = 'anger';
    public const URGENT = 'urgent';
    public const CONFUSION = 'confusion';
    public const REPEATED = 'repeated_explanation';
    public const PRICE = 'price';
    public const PRICE_OBJECTION = 'price_objection';
    public const DISCOUNT = 'discount_request';
    public const CHURN = 'churn';
    public const PAYMENT_ISSUE = 'payment_issue';
    public const COMPETITOR = 'competitor';
    public const THANKS = 'thanks';
    public const GREETING = 'greeting';
    public const WHAT_IS = 'what_is_yoka';
    public const HOW_TO = 'how_to';
    public const NAME = 'asks_name';
    public const BOT = 'asks_if_bot';
    public const STOP = 'stop_contact';

    private const PATTERNS = [
        self::HUMAN => '/\b(bicara|ngobrol|chat|telp|telepon|hubung\w*|sambung\w*|minta|mau|pengen|pingin)\b.{0,25}\b(orang|manusia|admin|cs|customer service|petugas|staf|staff|owner|pemilik|atasan|manager|manajer)\b(?!\s*(kasir|toko))|\b(bukan|jangan) (bot|robot|ai|mesin)\b|\bhuman\b/iu',
        self::COMPLAINT => '/\b(error|eror|erorr|gagal|nggak bisa|ga bisa|gak bisa|tidak bisa|tdk bisa|nggak mau|ga mau|gak mau|tidak mau|macet|ngadat|hang|lemot|lambat|crash|force close|keluar sendiri|hilang|ilang|salah|rusak|bermasalah|masalah|kendala|bug|kecewa|zonk|ribet)\b/iu',
        self::ANGER => '/(!{2,}|\b(rugi|parah|kesel|kesal|marah|kecewa banget|payah|bodoh|goblok|anjir|anjing|bangsat|tolol|nyesel|menyesal|ngecewain|mengecewakan|keterlaluan|penipu)\b)/iu',
        self::URGENT => '/\b(urgent|darurat|segera|cepat|cepet|buruan|sekarang juga|lagi rame|lagi ramai|antri|antre|nggak bisa transaksi|ga bisa transaksi|gak bisa transaksi|tidak bisa transaksi)\b/iu',
        self::CONFUSION => '/\b(bingung|gimana caranya|maksudnya|nggak ngerti|ga ngerti|gak ngerti|tidak paham|nggak paham|kurang paham)\b/iu',
        self::REPEATED => '/\b(sudah|udah|dah) (aku |saya |gue )?(jelasin|jelaskan|bilang|kasih tau|kasih tahu|cerita|kirim)\b|\bkok (ditanya|tanya) (lagi|ulang)\b|\b(ulang|lagi) terus\b/iu',
        self::PRICE => '/\b(harga|harganya|biaya|tarif|berapa(an)?|paket|langganan|bayar(nya)?|per bulan|perbulan|bulanan)\b/iu',
        self::PRICE_OBJECTION => '/\b(mahal|kemahalan|kemahalan|kurang murah|ada yang lebih murah|budget|bujet|dana terbatas|nggak kuat bayar)\b/iu',
        self::DISCOUNT => '/\b(diskon|discount|potongan|promo|voucher|kode promo|cashback|nego|kurangin harga|gratisin)\b/iu',
        self::CHURN => '/\b(berhenti (pakai|langganan|berlangganan)|nggak mau pakai|ga mau pakai|gak mau pakai|tidak mau pakai|udah nggak mau|sudah tidak mau|mau (berhenti|cabut|unsubscribe)|batalin langganan|batal(kan)? langganan|pindah (ke )?(aplikasi|app|pos) lain|hapus akun)\b/iu',
        self::PAYMENT_ISSUE => '/\b(sudah|udah|dah) (bayar|transfer|tf|lunas)\b|\b(belum|blm) (aktif|masuk|keaktif|ke-aktif)\b|\bbayar.{0,20}(belum|tidak|nggak|ga|gak) (aktif|masuk)\b/iu',
        self::COMPETITOR => '/\b(moka|majoo|pawoon|olsera|esb|qasir|kasir pintar|kasirpintar|ipos|youtap|bandingin|dibanding|banding(kan)?|vs|lebih bagus mana)\b/iu',
        self::THANKS => '/^\W*(ok(e|ey|ay)?|sip|siap|baik|mantap|makasih|terima ?kasih|thanks|thx|tq|udah bisa|sudah bisa|berhasil|beres|aman)\b.{0,40}$/iu',
        self::GREETING => '/^\W*(p|halo|hallo|hai|hi|hello|pagi|siang|sore|malam|assalamu.?alaikum|permisi|kak|min|gan)\W*(kak|min|gan)?\W*$/iu',
        self::WHAT_IS => '/\b(yoka( ?pos)? (itu )?(apa|aplikasi apa)|aplikasi apa (ya|sih)?|apa itu yoka)\b/iu',
        self::HOW_TO => '/\b(cara|gimana|bagaimana|langkah|setting|atur|nyambungin|sambungin|connect|konek|pasang|install|instal)\b/iu',
        self::NAME => '/\b(atas nama siapa|dengan siapa|sama siapa|namanya siapa|nama kamu|nama kakak|ini siapa)\b/iu',
        self::BOT => '/\b(kamu|kakak|ini) (bot|robot|ai|mesin)\b|\b(bot|robot|ai) (ya|kah|bukan)\b|\bbalas otomatis\b/iu',
        self::STOP => '/\b(jangan (hubungi|chat|kirim|wa) (saya|aku|lagi)|stop (kirim|chat|promo)|berhenti kirim)\b/iu',
    ];

    /** @return array<int, string> */
    public static function detect(string $text): array
    {
        $text = trim($text);
        $found = [];
        foreach (self::PATTERNS as $signal => $pattern) {
            if (preg_match($pattern, $text)) {
                $found[] = $signal;
            }
        }
        // "berapa" juga muncul di pertanyaan non-harga ("outletnya berapa"); butuh kata harga yang jelas.
        if (in_array(self::PRICE, $found, true) && ! preg_match('/\b(harga|biaya|tarif|paket|langganan|bulan|bayar)\b/iu', $text)) {
            $found = array_values(array_diff($found, [self::PRICE]));
        }
        // "Bisa bikin promo happy hour?" = tanya fitur Promo & Diskon untuk pembeli toko, bukan minta diskon langganan.
        if (in_array(self::DISCOUNT, $found, true) && preg_match('/\b(bikin|buat|atur|setting|pasang|fitur|menu|kasih ke (pembeli|pelanggan|customer)) ?(promo|diskon|voucher)|\b(promo|diskon) (untuk|ke|buat) (pembeli|pelanggan|customer|member)|happy hour|harga coret/iu', $text)) {
            $found = array_values(array_diff($found, [self::DISCOUNT]));
        }
        // Ucapan terima kasih yang juga mengeluh bukan penutup.
        if (in_array(self::THANKS, $found, true) && array_intersect($found, [self::COMPLAINT, self::ANGER, self::CHURN]) && ! preg_match('/\b(udah|sudah) bisa\b|\bberhasil\b/iu', $text)) {
            $found = array_values(array_diff($found, [self::THANKS]));
        }

        return $found;
    }

    /** Keluhan dalam bentuk apa pun: masalah teknis, kecewa, pembayaran, atau mengulang penjelasan. */
    public static function isComplaint(array $signals): bool
    {
        return (bool) array_intersect($signals, [self::COMPLAINT, self::ANGER, self::PAYMENT_ISSUE, self::REPEATED]);
    }
}
