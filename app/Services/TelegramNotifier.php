<?php

namespace App\Services;

use App\Models\Business;
use App\Models\CashierShift;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Menyusun dan mengirim notifikasi Telegram per bisnis. Semua method aman
 * dipanggil dari alur kasir: kegagalan hanya dicatat di log, tidak pernah
 * melempar error ke pemanggil.
 */
class TelegramNotifier
{
    /** @return \Illuminate\Support\Collection<int, Business> bisnis aktif yang sudah menautkan Telegram */
    public static function linkedBusinesses()
    {
        return Business::where('is_active', true)->get()->filter(fn (Business $b) => $b->telegramLinked());
    }

    // ── Pesan harian ─────────────────────────────────────────────────────────
    public static function dailySummary(Business $biz): void
    {
        if (!$biz->telegramWants('daily')) return;
        self::guard(function () use ($biz) {
            $d = app(DashboardService::class);
            $s = $d->getSummary($biz);
            if ($s['todayOrders'] === 0) return;

            self::deliver($biz, self::summaryText($biz, $s, $d->getTopProducts($biz, null, 5), count($d->getLowStockIngredients($biz)), 'Ringkasan hari ini'));
        });
    }

    /** Teks ringkasan; dipakai juga oleh perintah /ringkasan. */
    public static function summaryText(Business $biz, array $s, array $top, int $lowCount, string $title): string
    {
        $e = [TelegramService::class, 'esc'];
        $lines = [
            '📊 <b>' . $title . '</b> · ' . $e($biz->name),
            now()->isoFormat('dddd, D MMMM Y'),
            '',
            'Omzet: <b>' . TelegramService::rp($s['todayRevenue']) . '</b>',
            'Transaksi: <b>' . number_format($s['todayOrders']) . '</b>',
            'Rata-rata order: ' . TelegramService::rp($s['avgOrder']),
            'Estimasi profit: ' . TelegramService::rp($s['todayProfit']),
        ];

        if ($top) {
            $lines[] = '';
            $lines[] = '🏆 <b>Terlaris</b>';
            foreach ($top as $i => $p) {
                $lines[] = ($i + 1) . '. ' . $e($p['product_name']) . ' · ' . number_format($p['total_qty'], 0, ',', '.') . ' terjual';
            }
        }
        if ($lowCount > 0) {
            $lines[] = '';
            $lines[] = '⚠️ ' . $lowCount . ' bahan baku menipis. Ketik /stok untuk detail.';
        }
        return implode("\n", $lines);
    }

    // ── Alert ────────────────────────────────────────────────────────────────
    public static function lowStock(Business $biz): void
    {
        if (!$biz->telegramWants('alerts')) return;
        self::guard(function () use ($biz) {
            $items = app(DashboardService::class)->getLowStockIngredients($biz);
            if (!$items) return;

            // Sekali sehari saja, supaya cek tiap 6 jam tidak membanjiri chat.
            if (!Cache::add("tg:lowstock:{$biz->id}:" . today()->toDateString(), 1, now()->endOfDay())) return;

            self::deliver($biz, self::stockText($biz, $items, '⚠️ <b>Stok menipis</b>'));
        });
    }

    public static function stockText(Business $biz, array $items, string $title): string
    {
        $lines = [$title . ' · ' . TelegramService::esc($biz->name), ''];
        foreach (array_slice($items, 0, 15) as $i) {
            $lines[] = '• ' . TelegramService::esc($i['name']) . ': ' . number_format($i['current_stock'], 1) . ' ' . TelegramService::esc($i['unit']);
        }
        if (count($items) > 15) $lines[] = '… dan ' . (count($items) - 15) . ' lainnya';
        return implode("\n", $lines);
    }

    public static function shiftUnclosed(Business $biz): void
    {
        if (!$biz->telegramWants('alerts')) return;
        self::guard(function () use ($biz) {
            $shifts = CashierShift::where('business_id', $biz->id)->where('status', 'open')
                ->where('opened_at', '<=', now()->subHours(12))->with(['user', 'outlet'])->get();

            foreach ($shifts as $shift) {
                if (!Cache::add("tg:shift:{$shift->id}:" . today()->toDateString(), 1, now()->endOfDay())) continue;
                self::deliver($biz, '⏰ <b>Shift belum ditutup</b>' . "\n"
                    . TelegramService::esc($shift->user->name ?? '-') . ' · ' . TelegramService::esc($shift->outlet->name ?? '-') . "\n"
                    . 'Dibuka ' . $shift->opened_at->locale('id')->diffForHumans() . '.');
            }
        });
    }

    // ── Order ────────────────────────────────────────────────────────────────
    public static function orderPaid(Order $order): void
    {
        // Dikirim setelah respons kasir selesai, jadi tidak memperlambat checkout.
        dispatch(function () use ($order) {
            $biz = $order->business()->first();
            if (!$biz || !$biz->telegramWants('orders')) return;
            self::guard(fn () => self::deliver($biz,
                '🧾 <b>Order baru</b> ' . TelegramService::esc($order->order_number) . "\n"
                . TelegramService::rp($order->grand_total) . ' · ' . TelegramService::esc(ucfirst((string) $order->payment_method)) . "\n"
                . 'Kasir: ' . TelegramService::esc($order->user->name ?? '-')));
        })->afterResponse();
    }

    /** $kind: 'void' | 'refund' */
    public static function orderReversed(Order $order, string $kind, ?string $reason): void
    {
        dispatch(function () use ($order, $kind, $reason) {
            $biz = $order->business()->first();
            if (!$biz || !$biz->telegramWants('alerts')) return;
            self::guard(fn () => self::deliver($biz,
                ($kind === 'void' ? '🚫 <b>Order dibatalkan (void)</b>' : '↩️ <b>Order di-refund</b>') . "\n"
                . TelegramService::esc($order->order_number) . ' · ' . TelegramService::rp($order->grand_total) . "\n"
                . 'Alasan: ' . TelegramService::esc($reason ?: '-')));
        })->afterResponse();
    }

    // ── Util ─────────────────────────────────────────────────────────────────
    public static function deliver(Business $biz, string $html): bool
    {
        $chat = $biz->telegram()['chat_id'] ?? null;
        return $chat ? app(TelegramService::class)->send($chat, $html) : false;
    }

    private static function guard(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::warning('Telegram notifier error', ['error' => $e->getMessage()]);
        }
    }
}
