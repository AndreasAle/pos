<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\CashierShift;
use App\Services\DashboardService;
use App\Services\TelegramNotifier;
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
        protected DashboardService $dash,
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

        if (!$chatId || $text === '' || $text[0] !== '/') {
            return response()->json(['ok' => true]);
        }

        // "/omzet@NamaBot arg" -> command "omzet", arg "arg"
        [$cmd, $arg] = array_pad(preg_split('/\s+/', $text, 2), 2, '');
        $cmd = strtolower(explode('@', ltrim($cmd, '/'))[0]);

        try {
            $this->tg->send($chatId, $this->reply($cmd, trim($arg), (string) $chatId, (string) data_get($msg, 'chat.title', data_get($msg, 'from.first_name', ''))));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['ok' => true]);
    }

    private function reply(string $cmd, string $arg, string $chatId, string $chatName): string
    {
        if ($cmd === 'start' && $arg !== '') {
            return $this->link($arg, $chatId, $chatName);
        }

        $biz = Business::where('is_active', true)->where('settings->telegram->chat_id', $chatId)->first();

        if (!$biz) {
            return "Halo! Chat ini belum terhubung ke akun Yoka POS.\n\n"
                . "Buka <b>Pengaturan → Bot Telegram</b> di Yoka POS, klik <b>Buat tautan</b>, lalu tekan tombol Buka Telegram.";
        }

        return match ($cmd) {
            'start', 'help', 'bantuan' => $this->help($biz),
            'omzet', 'ringkasan'       => $this->summary($biz),
            'terlaris'                 => $this->top($biz),
            'stok'                     => $this->stock($biz),
            'shift'                    => $this->shift($biz),
            'putus'                    => $this->unlink($biz),
            default                    => "Perintah tidak dikenal. Ketik /help untuk daftar perintah.",
        };
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

        return "✅ <b>Terhubung!</b>\nChat ini sekarang menerima notifikasi untuk <b>" . TelegramService::esc($biz->name) . "</b>.\n\n" . $this->help($biz);
    }

    private function help(Business $biz): string
    {
        return "<b>Perintah Yoka POS</b>\n"
            . "/omzet - ringkasan penjualan hari ini\n"
            . "/terlaris - 5 produk terlaris hari ini\n"
            . "/stok - bahan baku yang menipis\n"
            . "/shift - shift yang sedang berjalan\n"
            . "/putus - putuskan koneksi chat ini\n"
            . "/help - tampilkan daftar ini";
    }

    private function summary(Business $biz): string
    {
        return TelegramNotifier::summaryText(
            $biz,
            $this->dash->getSummary($biz),
            $this->dash->getTopProducts($biz, null, 5),
            count($this->dash->getLowStockIngredients($biz)),
            'Penjualan hari ini'
        );
    }

    private function top(Business $biz): string
    {
        $top = $this->dash->getTopProducts($biz, null, 5);
        if (!$top) return 'Belum ada produk terjual hari ini.';

        $lines = ['🏆 <b>Terlaris hari ini</b>', ''];
        foreach ($top as $i => $p) {
            $lines[] = ($i + 1) . '. ' . TelegramService::esc($p['product_name'])
                . ' · ' . number_format($p['total_qty'], 0, ',', '.') . ' terjual · ' . TelegramService::rp($p['total_revenue']);
        }
        return implode("\n", $lines);
    }

    private function stock(Business $biz): string
    {
        $items = $this->dash->getLowStockIngredients($biz);
        return $items ? TelegramNotifier::stockText($biz, $items, '⚠️ <b>Stok menipis</b>') : '✅ Semua stok bahan baku aman.';
    }

    private function shift(Business $biz): string
    {
        $shifts = CashierShift::where('business_id', $biz->id)->where('status', 'open')->with(['user', 'outlet'])->get();
        if ($shifts->isEmpty()) return 'Tidak ada shift yang sedang berjalan.';

        $lines = ['⏱ <b>Shift berjalan</b>', ''];
        foreach ($shifts as $s) {
            $lines[] = '• ' . TelegramService::esc($s->user->name ?? '-') . ' · ' . TelegramService::esc($s->outlet->name ?? '-')
                . ' · dibuka ' . $s->opened_at->diffForHumans() . ' · modal ' . TelegramService::rp($s->opening_cash);
        }
        return implode("\n", $lines);
    }

    private function unlink(Business $biz): string
    {
        $biz->setTelegram(null);
        return 'Koneksi diputus. Chat ini tidak akan menerima notifikasi lagi.';
    }
}
