<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Services\TelegramAssistant;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Menerima update dari Telegram. Hanya chat yang sudah ditautkan ke sebuah
 * bisnis yang boleh membaca data; selain /start KODE, semua perintah lain
 * dari chat asing dijawab dengan petunjuk menautkan.
 */
class TelegramWebhookController extends Controller
{
    public function __construct(
        protected TelegramService $tg,
        protected TelegramAssistant $assistant,
    ) {}

    public function handle(Request $request)
    {
        $secret = (string) config('services.telegram.webhook_secret');
        if ($secret === '' || !hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            abort(403);
        }

        $msg    = $request->input('message');
        $chatId = data_get($msg, 'chat.id');
        $text   = trim((string) data_get($msg, 'text', ''));

        if (!$chatId || $text === '') {
            return response()->json(['ok' => true]);
        }

        try {
            [$reply, $linked] = $this->reply($text, (string) $chatId, (string) data_get($msg, 'chat.title', data_get($msg, 'from.first_name', '')));
            $this->tg->send($chatId, $reply, $linked);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['ok' => true]);
    }

    /** @return array{0:string,1:bool} [teks balasan, apakah chat sudah terhubung (untuk tombol cepat)] */
    private function reply(string $text, string $chatId, string $chatName): array
    {
        // "/omzet@NamaBot arg" -> perintah "omzet", sisanya argumen. Teks biasa dianggap pertanyaan.
        $isCmd = $text[0] === '/';
        $cmd   = '';
        $arg   = '';
        if ($isCmd) {
            [$c, $arg] = array_pad(preg_split('/\s+/', $text, 2), 2, '');
            $cmd = strtolower(explode('@', ltrim($c, '/'))[0]);
            $arg = trim($arg);
        }

        if ($cmd === 'start' && $arg !== '') {
            return [$this->link($arg, $chatId, $chatName), true];
        }

        $biz = Business::where('is_active', true)->where('settings->telegram->chat_id', $chatId)->first();

        if (!$biz) {
            return ["Halo! Chat ini belum terhubung ke akun Yoka POS.

"
                . "Buka <b>Pengaturan → Bot Telegram</b> di Yoka POS, klik <b>Hubungkan Telegram</b>, lalu tekan tombol Buka Telegram.", false];
        }

        if ($cmd === 'putus') {
            return [$this->unlink($biz), false];
        }

        // Perintah lama tetap jalan, tapi pengguna tidak perlu tahu: semuanya lewat asisten.
        $question = match ($cmd) {
            ''                     => $text,
            'start', 'help', 'bantuan' => 'bantuan',
            'omzet', 'ringkasan'   => 'ringkasan hari ini',
            'terlaris'             => 'produk terlaris hari ini',
            'stok'                 => 'stok menipis',
            'shift'                => 'shift berjalan',
            default                => ltrim($text, '/'),
        };

        return [$this->assistant->answer($biz, $question), true];
    }

    private function link(string $code, string $chatId, string $chatName): string
    {
        $code  = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
        $bizId = Cache::pull('tg-link:' . $code);
        $biz   = $bizId ? Business::find($bizId) : null;

        if (!$biz) {
            return "Kode tautan tidak valid atau sudah kedaluwarsa. Buat tautan baru dari <b>Pengaturan → Bot Telegram</b>.";
        }

        // Satu chat hanya boleh milik satu bisnis.
        Business::where('settings->telegram->chat_id', $chatId)->where('id', '!=', $biz->id)->get()
            ->each(fn (Business $b) => $b->setTelegram(null));

        $biz->setTelegram([
            'chat_id'   => $chatId,
            'chat_name' => $chatName,
            'linked_at' => now()->toIso8601String(),
            'prefs'     => $biz->telegram()['prefs'] ?? [],
        ]);

        return "✅ <b>Terhubung!</b>
Chat ini sekarang menerima laporan dan peringatan untuk <b>" . TelegramService::esc($biz->name) . "</b>.

" . $this->assistant->help();
    }

    private function unlink(Business $biz): string
    {
        $biz->setTelegram(null);
        return 'Koneksi diputus. Chat ini tidak akan menerima notifikasi lagi.';
    }
}
