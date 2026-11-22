<?php

namespace App\Services\PlatformAgents\Sales;

use App\Models\PlatformAiProspect;
use App\Models\PlatformAiWhatsAppMessage;

/**
 * Memutuskan TUJUAN balasan sebelum model menulis: menjawab, menggali, membangun kepercayaan, menutup, dsb.
 * Juga memutuskan apakah boleh bertanya dan apakah link trial pantas disebut.
 */
final class ConversationStrategy
{
    public const PRICE = '/\b(harga|harganya|berapa(an)?|biaya|tarif|per bulan|perbulan|paket(nya)? apa)\b/iu';

    public const HARDWARE = '/\b(printer|print|struk|thermal|58 ?mm|80 ?mm|laci (uang|kasir)|cash drawer|scanner|barcode|tablet|mesin kasir|hardware|perangkat)\b/iu';

    /** Minta paket aplikasi + perangkat atau perangkat gratis (bukan sekadar tanya "dapat printer?"). */
    public const BUNDLE = '/\b(printer|tablet|perangkat|mesin kasir)\b.{0,30}\b(sekalian|bundling|bundel)\b|\b(sekalian|sama|plus|dengan)\b.{0,15}\b(printer|tablet|perangkat)\b.{0,30}\b(berapa|harga(nya)?)\b|\bpaket (printer|hardware|lengkap)\b|\b(printer|tablet)(nya)? gratis\b|\bgratis(in)? (printer|tablet)\b/iu';

    /** Niat membeli/berlangganan sekarang: beri langkah daftar & bayar yang resmi, jangan "ditanyakan ke tim". */
    public const BUY = '/\b(mau|pengen|ingin|siap|udah mau|sudah mau)\s+(langsung\s+)?(langganan|berlangganan|beli|bayar|daftar|pakai|pake|order)\b|\b(cara|gimana|bagaimana)\s+(daftar|langganan|berlangganan|bayar|beli|upgrade)(nya)?\b|\bdaftar(nya)?\s+(di ?mana|dimana|kemana|lewat mana|ke website)\b|\blink (daftar|langganan|pembayaran)\b|\blangsung (aja )?(daftar|langganan|bayar)\b|\b(website|web|situs)\b.{0,20}\bdaftar|\bdaftar\b.{0,20}\b(website|web|situs)\b/iu';

    public const BUY_STEPS = 'caranya gampang kak: daftar dulu di yokapos.com/register, gratis 7 hari. habis login masuk menu Langganan, pilih paketnya, terus bayar lewat Midtrans. begitu lunas langsung aktif.';

    public const TRIAL_REQUEST = '/\b(mau|pengen|ingin|boleh|bisa|gimana|cara) (coba|nyoba|trial|daftar)\b|\bcoba gratis\b|\blink (daftar|trial)\b/iu';

    public const CLOSING = '/\b(nanti (aku|saya|gue|gw)?\s*(kabar(i|in)|hubungi|chat|kontak|info)|(pikir|pikir-pikir|diskusi(kan)?|rundingan) dulu|(makasih|terima ?kasih|thanks|thx)( (ya|kak|min|banyak))*\s*$|udah dulu|sudah dulu|cukup dulu|lain kali)\b/iu';

    public const TRUST = ['PREVIOUS_BAD_EXPERIENCE', 'LOW_TRUST'];

    /**
     * @return array{objective:string, closing:?string, allow_question:bool, allow_trial_cta:bool, hardware:bool, bundle:bool, rules:array<int,string>}
     */
    public function decide(PlatformAiProspect $p, string $text, array $objectionTypes, ?array $commercial, bool $fieldVisit = false): array
    {
        $trialSent = $p->phone_normalized && PlatformAiWhatsAppMessage::where('phone', $p->phone_normalized)->where('direction', 'outbound')
            ->where('body', 'like', '%yokapos.com/register%')->exists();
        $buying = (bool) preg_match(self::BUY, $text);
        $trialAsked = (bool) preg_match(self::TRIAL_REQUEST, $text);
        $hardware = (bool) preg_match(self::HARDWARE, $text);
        $bundle = (bool) preg_match(self::BUNDLE, $text);
        $closing = null;
        if (in_array('REQUEST_STOP', $objectionTypes, true)) {
            $closing = 'OPTED_OUT';
        } elseif (in_array('NO_CURRENT_NEED', $objectionTypes, true)) {
            $closing = 'NOT_INTERESTED';
        } elseif (preg_match(self::CLOSING, $text) && ! preg_match('/\?/', $text)) {
            $closing = 'CUSTOMER_WILL_RECONTACT';
        }

        $objective = match (true) {
            $closing !== null => 'CLOSE_CONVERSATION',
            $fieldVisit => 'ARRANGE_FIELD_VISIT',
            in_array('REQUEST_HUMAN', $objectionTypes, true) || ($commercial['needs_approval'] ?? false) => 'REQUEST_HUMAN_APPROVAL',
            (bool) array_intersect(self::TRUST, $objectionTypes) => 'BUILD_TRUST',
            $buying => 'READY_TO_BUY',
            $trialAsked => 'OFFER_TRIAL',
            $hardware => 'IDENTIFY_HARDWARE_INTEREST',
            (bool) preg_match(self::PRICE, $text) => 'ANSWER_DIRECT_QUESTION',
            $objectionTypes !== [] => 'HANDLE_OBJECTION',
            str_contains($text, '?') => 'ANSWER_DIRECT_QUESTION',
            default => 'DISCOVER_NEED',
        };
        $qualified = ($p->intelligence['qualification']['category'] ?? null) === 'qualified';
        $allowTrial = $buying || $trialAsked || ($objective === 'ANSWER_DIRECT_QUESTION' && preg_match(self::PRICE, $text) && ! $trialSent)
            || ($qualified && ! $trialSent && in_array($objective, ['DISCOVER_NEED', 'ANSWER_DIRECT_QUESTION'], true));
        $allowQuestion = ! in_array($objective, ['CLOSE_CONVERSATION', 'OFFER_TRIAL', 'READY_TO_BUY', 'REQUEST_HUMAN_APPROVAL', 'ARRANGE_FIELD_VISIT'], true);

        $rules = match ($objective) {
            'CLOSE_CONVERSATION' => match ($closing) {
                'OPTED_OUT' => ['Satu kalimat sopan bahwa kamu tidak akan menghubungi lagi. Tanpa tawaran apa pun.'],
                'NOT_INTERESTED' => ['Terima dengan sopan, tanpa membujuk. Boleh bilang dia bisa kembali kapan saja. Tanpa pertanyaan.'],
                default => ['Dia mau menyudahi obrolan. Tutup dengan hangat: terima kasih, sebut singkat SATU kebutuhan yang tadi dia ceritakan dan bahwa kamu bisa bantu tunjukkan alurnya kalau nanti mau lanjut. Tanpa pertanyaan, tanpa link, tanpa jualan baru. Jangan cuma "oke siap".'],
            },
            'ARRANGE_FIELD_VISIT' => ['Dia setuju didatangi/minta demo langsung. Terima kasih, bilang sudah kamu teruskan ke tim Yoka untuk mengatur waktunya. JANGAN menyebut jam, tanggal, atau siapa yang datang.'],
            'BUILD_TRUST' => ['Dia punya pengalaman buruk/ragu. JANGAN langsung menyebut fitur atau klaim layanan. Akui kekhawatirannya secara spesifik (bukan "paham banget"), lalu tanyakan apa persisnya masalah waktu itu (aplikasinya error, data hilang, atau bantuannya sulit dihubungi). Jangan menjanjikan layanan support 24 jam, respon cepat, atau tim tertentu.'],
            'READY_TO_BUY' => ['Dia mau langsung daftar/berlangganan. Ini peluang closing: JANGAN bilang "tanyakan ke tim" atau "teruskan ke tim". Kasih tahu caranya dengan kata-katamu sendiri, santai dan singkat seperti chat WA (BUKAN daftar langkah formal). Fakta yang wajib benar: daftar di yokapos.com/register (gratis trial 7 hari), setelah login buka menu Langganan, pilih paket, bayar lewat Midtrans, aktif setelah lunas. Boleh sebut satu paket yang cocok dari PAKET AKTIF. Tanpa pertanyaan discovery.'],
            'OFFER_TRIAL' => ['Dia minta coba. Langsung beri langkahnya: daftar di yokapos.com/register, trial gratis 7 hari. Tanpa pertanyaan tambahan yang menghambat.'],
            'IDENTIFY_HARDWARE_INTEREST' => array_values(array_filter([
                'Jawab langsung: langganan Yoka POS tidak termasuk printer atau perangkat. '.app(HardwareCatalog::class)->promptFacts(),
                'Cara cetak yang didukung: dialog print browser (printer USB/yang terpasang di perangkat) atau printer Bluetooth lewat aplikasi RawBT di Android. Jangan menjamin semua printer 58mm cocok; sarankan cek tipe printernya saat trial.',
                $bundle ? (app(HardwareCatalog::class)->approvedBundle() ? 'Dia minta paket aplikasi + printer: sebut HANYA bundel resmi di atas beserta harganya.' : 'Dia minta paket aplikasi + printer: bilang kamu mintakan penawaran ke tim dulu, tanpa harga atau janji.') : 'Boleh tanyakan SATU hal: apakah sudah punya printer, atau mau sekalian beli.',
                'Panduan resmi (boleh dikirim bila relevan): yokapos.com/panduan/menyambungkan-printer-struk.',
            ])),
            'ANSWER_DIRECT_QUESTION' => ['Jawab pertanyaannya dulu secara langsung dan singkat. Kalau tanya harga: sebut harga dari PAKET AKTIF saja.'],
            'REQUEST_HUMAN_APPROVAL' => ['Bilang kamu cek dulu ke tim. Jangan janji hasil atau waktu.'],
            'HANDLE_OBJECTION' => ['Tangani keberatannya dulu sebelum menyebut fitur lain.'],
            default => ['Tanggapi ceritanya dulu (singkat), baru boleh satu pertanyaan yang relevan.'],
        };
        $rules[] = $allowQuestion ? 'Paling banyak SATU pertanyaan di balasan ini, dan tidak wajib bertanya.' : 'Jangan bertanya di balasan ini.';
        $rules[] = $allowTrial ? ($trialSent ? 'Link trial sudah pernah dikirim; sebut lagi hanya bila dia minta.' : 'Link trial boleh disebut sekali bila pas.') : 'Jangan menyebut link trial/daftar di balasan ini.';

        return ['objective' => $objective, 'closing' => $closing, 'allow_question' => $allowQuestion, 'allow_trial_cta' => (bool) $allowTrial,
            'hardware' => $hardware, 'bundle' => $bundle, 'rules' => $rules];
    }
}
