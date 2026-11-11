<?php

namespace App\Services;

use App\Models\Business;
use App\Models\CashierShift;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menjawab pertanyaan owner dengan bahasa sehari-hari ("omzet kemarin berapa?",
 * "stok susu masih ada?"). Murni aturan kata kunci, tanpa layanan AI luar,
 * jadi gratis, cepat, dan datanya tidak keluar dari server.
 *
 * Alur: normalisasi teks -> baca periode (hari ini, kemarin, bulan lalu, ...) ->
 * pilih maksud (omzet, laba, terlaris, stok, ...) -> jawab dengan data bisnis.
 */
class TelegramAssistant
{
    /** Pertanyaan contoh yang juga dipakai sebagai tombol cepat. */
    public const QUICK = [
        'Omzet hari ini',
        'Produk terlaris',
        'Stok menipis',
        'Shift berjalan',
        'Ringkasan bulan ini',
    ];

    public function answer(Business $biz, string $raw): string
    {
        // Singkatan & salah ketik dibereskan dulu ("brp transaski" -> "berapa transaksi").
        $semi = $this->expandSlang($this->normalize($raw));
        $text = $this->fixTypos($semi);
        [$from, $to, $label, $explicit] = $this->period($text);

        // Pertanyaan spesifik lebih dulu: "es kopi susu terjual berapa?"
        if ($this->has($text, ['terjual', 'laku', 'kejual', 'berapa porsi', 'berapa cup', 'berapa gelas', 'berapa pcs', 'penjualan'])
            && ($name = $this->matchProduct($biz, $semi))) {
            return $this->productSales($biz, $name, $from, $to, $label);
        }
        if ($this->has($text, ['stok', 'stock', 'sisa', 'persediaan', 'masih ada', 'tinggal berapa'])
            && ($ing = $this->matchIngredient($biz, $semi))) {
            return $this->ingredientStock($ing);
        }

        return match (true) {
            $this->has($text, ['terima kasih', 'makasih', 'thanks', 'thx', 'tengkyu', 'mantap', 'oke deh', 'sip'])
                => 'Sama-sama! Kalau ada yang mau dicek lagi, tanya aja ya.',
            $this->has($text, ['bantuan', 'bisa apa', 'help', 'tanya apa', 'cara pakai', 'menu bot', 'fitur'])
                => $this->help(),
            $this->has($text, ['ringkasan', 'laporan', 'rekap', 'gimana toko', 'bagaimana toko', 'kondisi toko', 'kabar toko', 'perkembangan'])
                => $this->summary($biz, $from, $to, $label),
            $this->has($text, ['terlaris', 'paling laku', 'paling laris', 'best seller', 'bestseller', 'favorit', 'paling banyak terjual', 'paling banyak dibeli', 'paling banyak dipesan', 'andalan', 'laris'])
                => $this->top($biz, $from, $to, $label),
            $this->has($text, ['laba', 'untung', 'profit', 'keuntungan', 'margin'])
                => $this->profit($biz, $from, $to, $label),
            $this->has($text, ['qris', 'tunai', 'cash', 'debit', 'metode pembayaran', 'bayar pakai', 'pembayaran', 'transfer'])
                => $this->payments($biz, $from, $to, $label),
            $this->has($text, ['stok', 'stock', 'habis', 'menipis', 'persediaan', 'bahan baku', 'belanja'])
                => $this->lowStock($biz),
            $this->has($text, ['shift', 'kasir', 'siapa jaga', 'siapa yang jaga', 'lagi buka', 'sedang buka', 'toko buka'])
                => $this->shifts($biz),
            $this->has($text, ['transaksi', 'order', 'pesanan', 'nota', 'struk', 'pelanggan datang', 'pembeli', 'ramai'])
                => $this->orders($biz, $from, $to, $label),
            $this->has($text, ['omzet', 'omset', 'pendapatan', 'pemasukan', 'penghasilan', 'uang masuk', 'jualan', 'penjualan', 'dapat berapa', 'dapet berapa', 'laku berapa', 'income', 'revenue', 'sales'])
                => $this->revenue($biz, $from, $to, $label),
            $this->has($text, ['halo', 'hai', 'hi', 'hello', 'pagi', 'siang', 'sore', 'malam', 'assalamualaikum', 'permisi', 'p'])
                => "Halo! Aku asisten toko " . TelegramService::esc($biz->name) . ". Mau cek apa hari ini?\n\n" . $this->examples(),
            // Hanya sebut periode, mis. "kemarin" / "bulan lalu" -> anggap minta ringkasan
            $explicit => $this->summary($biz, $from, $to, $label),
            default   => "Maaf, aku belum paham maksudnya. 🙏\nCoba tanya dengan cara seperti ini:\n\n" . $this->examples(),
        };
    }

    // ── Jawaban ──────────────────────────────────────────────────────────────

    public function help(): string
    {
        return "Aku bisa bantu cek toko kamu. Tanya saja pakai bahasa biasa, atau tekan tombol di bawah.\n\n" . $this->examples()
            . "\n\nKamu juga bisa menyebut waktunya: <i>hari ini, kemarin, minggu ini, minggu lalu, bulan ini, bulan lalu</i>.";
    }

    private function examples(): string
    {
        return "• Omzet hari ini berapa?\n• Kemarin laku berapa transaksi?\n• Produk apa yang paling laris bulan ini?\n"
            . "• Untung minggu ini berapa?\n• Stok apa yang mau habis?\n• Es kopi susu terjual berapa hari ini?\n• Siapa yang lagi jaga kasir?";
    }

    private function revenue(Business $biz, Carbon $from, Carbon $to, string $label): string
    {
        $s = $this->stats($biz, $from, $to);
        if ($s['orders'] === 0) return "Belum ada transaksi {$label}.";

        $out = "💰 Omzet {$label}: <b>" . TelegramService::rp($s['revenue']) . "</b> dari {$s['orders']} transaksi.";
        return $out . $this->compare($biz, $from, $to, $s['revenue']);
    }

    private function orders(Business $biz, Carbon $from, Carbon $to, string $label): string
    {
        $s = $this->stats($biz, $from, $to);
        if ($s['orders'] === 0) return "Belum ada transaksi {$label}.";

        return "🧾 Ada <b>{$s['orders']} transaksi</b> {$label}, rata-rata " . TelegramService::rp($s['revenue'] / $s['orders']) . " per transaksi.";
    }

    private function profit(Business $biz, Carbon $from, Carbon $to, string $label): string
    {
        $s = $this->stats($biz, $from, $to, withProfit: true);
        if ($s['orders'] === 0) return "Belum ada transaksi {$label}, jadi belum ada laba yang bisa dihitung.";

        $margin = $s['revenue'] > 0 ? round($s['profit'] / $s['revenue'] * 100) : 0;
        return "📈 Estimasi laba {$label}: <b>" . TelegramService::rp($s['profit']) . "</b> (margin sekitar {$margin}%).\n"
            . "<i>Dihitung dari harga jual dikurangi harga modal produk.</i>";
    }

    private function top(Business $biz, Carbon $from, Carbon $to, string $label): string
    {
        $rows = $this->productTotals($biz, $from, $to, 5);
        if (!$rows) return "Belum ada produk terjual {$label}.";

        $lines = ["🏆 Produk paling laris {$label}:", ''];
        foreach ($rows as $i => $r) {
            $lines[] = ($i + 1) . '. ' . TelegramService::esc($r->product_name) . ' · ' . number_format($r->qty, 0, ',', '.') . ' terjual · ' . TelegramService::rp($r->total);
        }
        return implode("\n", $lines);
    }

    private function payments(Business $biz, Carbon $from, Carbon $to, string $label): string
    {
        $rows = Order::forBusiness($biz->id)->paid()->whereBetween('created_at', [$from, $to])
            ->select('payment_method', DB::raw('COUNT(*) c'), DB::raw('SUM(grand_total) t'))
            ->groupBy('payment_method')->orderByDesc('t')->get();
        if ($rows->isEmpty()) return "Belum ada transaksi {$label}.";

        $lines = ["💳 Pembayaran {$label}:", ''];
        foreach ($rows as $r) {
            $lines[] = '• ' . TelegramService::esc(ucfirst($r->payment_method ?: 'lainnya')) . ': ' . TelegramService::rp($r->t) . " ({$r->c} transaksi)";
        }
        return implode("\n", $lines);
    }

    private function lowStock(Business $biz): string
    {
        $items = Ingredient::forBusiness($biz->id)->lowStock()->where('is_active', true)->orderBy('name')->limit(15)->get()->toArray();
        if (!$items) return '✅ Aman, belum ada bahan baku yang menipis.';

        $lines = ['⚠️ Bahan yang mau habis, mungkin perlu belanja:', ''];
        foreach ($items as $i) {
            $lines[] = '• ' . TelegramService::esc($i['name']) . ': sisa ' . rtrim(rtrim(number_format($i['current_stock'], 2, ',', '.'), '0'), ',') . ' ' . TelegramService::esc($i['unit']);
        }
        return implode("\n", $lines);
    }

    private function ingredientStock(Ingredient $i): string
    {
        $low = (float) $i->current_stock <= (float) $i->minimum_stock;
        return ($low ? '⚠️' : '✅') . ' Stok <b>' . TelegramService::esc($i->name) . '</b> sisa '
            . rtrim(rtrim(number_format((float) $i->current_stock, 2, ',', '.'), '0'), ',') . ' ' . TelegramService::esc($i->unit)
            . ($low ? ', sudah di batas minimum. Sebaiknya segera belanja.' : '.');
    }

    private function shifts(Business $biz): string
    {
        $shifts = CashierShift::where('business_id', $biz->id)->where('status', 'open')->with(['user', 'outlet'])->get();
        if ($shifts->isEmpty()) return 'Saat ini tidak ada kasir yang sedang membuka shift.';

        $lines = ['⏱ Kasir yang sedang jaga:', ''];
        foreach ($shifts as $s) {
            $lines[] = '• ' . TelegramService::esc($s->user->name ?? '-') . ' di ' . TelegramService::esc($s->outlet->name ?? '-')
                . ', buka ' . $s->opened_at->locale('id')->diffForHumans() . ', modal awal ' . TelegramService::rp($s->opening_cash);
        }
        return implode("\n", $lines);
    }

    private function summary(Business $biz, Carbon $from, Carbon $to, string $label): string
    {
        $s = $this->stats($biz, $from, $to, withProfit: true);
        if ($s['orders'] === 0) return "Belum ada transaksi {$label}.";

        $lines = [
            '📊 <b>Ringkasan ' . $label . '</b> · ' . TelegramService::esc($biz->name),
            '',
            'Omzet: <b>' . TelegramService::rp($s['revenue']) . '</b>' . $this->compare($biz, $from, $to, $s['revenue'], short: true),
            'Transaksi: <b>' . $s['orders'] . '</b>, rata-rata ' . TelegramService::rp($s['revenue'] / $s['orders']),
            'Estimasi laba: ' . TelegramService::rp($s['profit']),
        ];
        $top = $this->productTotals($biz, $from, $to, 3);
        if ($top) {
            $lines[] = '';
            $lines[] = '🏆 Terlaris: ' . collect($top)->map(fn ($r) => TelegramService::esc($r->product_name) . ' (' . number_format($r->qty, 0, ',', '.') . ')')->join(', ');
        }
        return implode("\n", $lines);
    }

    private function productSales(Business $biz, string $name, Carbon $from, Carbon $to, string $label): string
    {
        $r = OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.business_id', $biz->id)->where('orders.status', 'paid')
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('order_items.product_name', $name)
            ->selectRaw('COALESCE(SUM(order_items.qty),0) qty, COALESCE(SUM(order_items.subtotal),0) total')->first();

        if ((float) $r->qty <= 0) return '<b>' . TelegramService::esc($name) . "</b> belum terjual {$label}.";
        return '🛒 <b>' . TelegramService::esc($name) . '</b> terjual <b>' . number_format($r->qty, 0, ',', '.') . "</b> {$label}, senilai " . TelegramService::rp($r->total) . '.';
    }

    // ── Data ─────────────────────────────────────────────────────────────────

    private function stats(Business $biz, Carbon $from, Carbon $to, bool $withProfit = false): array
    {
        $q = Order::forBusiness($biz->id)->paid()->whereBetween('created_at', [$from, $to]);
        $orders  = (clone $q)->count();
        $revenue = (float) (clone $q)->sum('grand_total');
        $profit  = $withProfit && $orders > 0
            ? (float) (clone $q)->with('items')->get()->sum(fn ($o) => $o->estimated_profit)
            : 0.0;

        return compact('orders', 'revenue', 'profit');
    }

    private function productTotals(Business $biz, Carbon $from, Carbon $to, int $limit): array
    {
        return OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.business_id', $biz->id)->where('orders.status', 'paid')
            ->whereBetween('orders.created_at', [$from, $to])
            ->selectRaw('order_items.product_name, SUM(order_items.qty) qty, SUM(order_items.subtotal) total')
            ->groupBy('order_items.product_name')->orderByDesc('qty')->limit($limit)->get()->all();
    }

    /** Bandingkan dengan periode sebelumnya yang sama panjang. */
    private function compare(Business $biz, Carbon $from, Carbon $to, float $revenue, bool $short = false): string
    {
        $len  = $from->diffInSeconds($to) + 1;
        $prev = $this->stats($biz, $from->copy()->subSeconds($len), $from->copy()->subSecond());
        if ($prev['revenue'] <= 0) return '';

        $pct = round(($revenue - $prev['revenue']) / $prev['revenue'] * 100);
        if ($pct === 0) return $short ? ' (sama seperti sebelumnya)' : "\nSama seperti periode sebelumnya.";
        $word = $pct > 0 ? "naik {$pct}%" : 'turun ' . abs($pct) . '%';
        return $short ? " ({$word})" : "\n" . ($pct > 0 ? '📈' : '📉') . " {$word} dibanding periode sebelumnya.";
    }

    /** Cari nama produk yang disebut di teks (nama terpanjang yang cocok). */
    private function matchProduct(Business $biz, string $text): ?string
    {
        $names = OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.business_id', $biz->id)->whereNotNull('order_items.product_name')
            ->distinct()->pluck('order_items.product_name');

        return $this->bestName($names, $text);
    }

    private function matchIngredient(Business $biz, string $text): ?Ingredient
    {
        $all  = Ingredient::forBusiness($biz->id)->where('is_active', true)->get();
        $name = $this->bestName($all->pluck('name'), $text);
        return $name ? $all->firstWhere('name', $name) : null;
    }

    private function bestName($names, string $text): ?string
    {
        $best = null; $bestLen = 0;
        foreach ($names as $n) {
            $norm = $this->normalize((string) $n);
            if (strlen($norm) < 3) continue;
            if (preg_match('/\b' . preg_quote($norm, '/') . '\b/u', $text) && strlen($norm) > $bestLen) {
                $best = $n; $bestLen = strlen($norm);
            }
        }
        return $best;
    }

    // ── Bahasa ───────────────────────────────────────────────────────────────

    /** Singkatan chat yang umum -> kata baku. */
    private const SLANG = [
        'brp' => 'berapa', 'brapa' => 'berapa', 'berapaan' => 'berapa', 'brpa' => 'berapa', 'bpa' => 'berapa',
        'ad' => 'ada', 'ni' => 'ini', 'nih' => 'ini', 'tu' => 'itu', 'yg' => 'yang', 'yng' => 'yang',
        'gmn' => 'gimana', 'gmna' => 'gimana', 'gimane' => 'gimana', 'bgmn' => 'bagaimana',
        'utk' => 'untuk', 'dgn' => 'dengan', 'krn' => 'karena', 'tdk' => 'tidak', 'gak' => 'tidak', 'ga' => 'tidak', 'nggak' => 'tidak',
        'udh' => 'sudah', 'udah' => 'sudah', 'sdh' => 'sudah', 'sy' => 'saya', 'aq' => 'aku', 'gw' => 'aku', 'gue' => 'aku',
        'kmrn' => 'kemarin', 'kmren' => 'kemarin', 'kemaren' => 'kemarin', 'kmaren' => 'kemarin',
        'bln' => 'bulan', 'blan' => 'bulan', 'mgg' => 'minggu', 'mggu' => 'minggu', 'mng' => 'minggu', 'thn' => 'tahun',
        'hr' => 'hari', 'hri' => 'hari', 'skrg' => 'sekarang', 'skrng' => 'sekarang', 'td' => 'tadi', 'lg' => 'lagi',
        'dpt' => 'dapat', 'dpet' => 'dapat', 'dapet' => 'dapat', 'bs' => 'bisa', 'blh' => 'boleh', 'tlg' => 'tolong',
        'trx' => 'transaksi', 'trans' => 'transaksi', 'transaki' => 'transaksi', 'stk' => 'stok', 'hbs' => 'habis', 'mnipis' => 'menipis',
        'omset' => 'omzet', 'pnjualan' => 'penjualan', 'pjualan' => 'penjualan',
        'kasr' => 'kasir', 'pembyaran' => 'pembayaran', 'tmksh' => 'terima kasih', 'mksh' => 'makasih', 'trims' => 'terima kasih',
    ];

    /** Kosakata yang dipakai pencocokan fuzzy untuk membetulkan salah ketik. */
    private const VOCAB = [
        'berapa', 'transaksi', 'omzet', 'pendapatan', 'pemasukan', 'penghasilan', 'penjualan', 'jualan', 'laba', 'untung', 'keuntungan', 'profit',
        'terlaris', 'terjual', 'produk', 'stok', 'stock', 'habis', 'menipis', 'persediaan', 'shift', 'kasir', 'ringkasan', 'laporan', 'rekap',
        'kemarin', 'minggu', 'bulan', 'tahun', 'sekarang', 'pembayaran', 'tunai', 'qris', 'bantuan', 'pesanan', 'pelanggan', 'bahan', 'belanja',
        'terima', 'kasih', 'makasih', 'halo', 'gimana', 'bagaimana', 'sudah', 'hari',
    ];

    private function expandSlang(string $text): string
    {
        $out = [];
        foreach (explode(' ', $text) as $w) {
            $out[] = self::SLANG[$w] ?? $w;
        }
        return implode(' ', $out);
    }

    /** Ganti kata yang mirip kosakata (selisih 1-2 huruf, termasuk huruf tertukar) dengan kata yang benar. */
    private function fixTypos(string $text): string
    {
        $out = [];
        foreach (explode(' ', $text) as $w) {
            $out[] = $this->closest($w) ?? $w;
        }
        return implode(' ', $out);
    }

    private function closest(string $w): ?string
    {
        $len = mb_strlen($w);
        if ($len < 4 || in_array($w, self::VOCAB, true) || ctype_digit($w)) return null;

        $max  = $len >= 7 ? 2 : 1;
        $best = null;
        $bestD = $max + 1;
        foreach (self::VOCAB as $v) {
            if (abs(mb_strlen($v) - $len) > $max) continue;
            $d = $this->editDistance($w, $v);
            if ($d < $bestD) { $bestD = $d; $best = $v; }
        }
        return $bestD <= $max ? $best : null;
    }

    /** Jarak edit Damerau-Levenshtein (tukar dua huruf bersebelahan dihitung 1). */
    private function editDistance(string $a, string $b): int
    {
        $a = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY);
        $b = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY);
        $n = count($a);
        $m = count($b);
        $d = [];
        for ($i = 0; $i <= $n; $i++) $d[$i][0] = $i;
        for ($j = 0; $j <= $m; $j++) $d[0][$j] = $j;
        for ($i = 1; $i <= $n; $i++) {
            for ($j = 1; $j <= $m; $j++) {
                $c = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + $c);
                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
                }
            }
        }
        return $d[$n][$m];
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower($s);
        $s = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /** True bila salah satu frasa muncul sebagai kata utuh. */
    private function has(string $text, array $phrases): bool
    {
        foreach ($phrases as $p) {
            if (preg_match('/\b' . preg_quote($p, '/') . '\b/u', $text)) return true;
        }
        return false;
    }

    /** @return array{0:Carbon,1:Carbon,2:string,3:bool} dari, sampai, label, apakah periode disebut eksplisit */
    private function period(string $t): array
    {
        $now = now();
        $day = fn (Carbon $d) => [$d->copy()->startOfDay(), $d->copy()->endOfDay()];

        return match (true) {
            $this->has($t, ['kemarin', 'kemaren'])                      => [...$day($now->copy()->subDay()), 'kemarin', true],
            $this->has($t, ['minggu lalu', 'pekan lalu', 'seminggu lalu']) => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek(), 'minggu lalu', true],
            $this->has($t, ['minggu ini', 'pekan ini'])                 => [$now->copy()->startOfWeek(), $now->copy()->endOfDay(), 'minggu ini', true],
            $this->has($t, ['7 hari', 'tujuh hari', 'seminggu'])        => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), '7 hari terakhir', true],
            $this->has($t, ['bulan lalu', 'bulan kemarin'])             => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth(), 'bulan lalu', true],
            $this->has($t, ['bulan ini'])                               => [$now->copy()->startOfMonth(), $now->copy()->endOfDay(), 'bulan ini', true],
            $this->has($t, ['30 hari', 'sebulan'])                      => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay(), '30 hari terakhir', true],
            $this->has($t, ['tahun ini'])                               => [$now->copy()->startOfYear(), $now->copy()->endOfDay(), 'tahun ini', true],
            $this->has($t, ['hari ini', 'sekarang', 'tadi', 'barusan']) => [...$day($now), 'hari ini', true],
            default                                                     => [...$day($now), 'hari ini', false],
        };
    }
}
