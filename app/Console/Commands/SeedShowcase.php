<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi sebuah bisnis dengan data contoh yang lengkap: menu, bahan baku, resep,
 * stok, pelanggan, dan riwayat transaksi berhari-hari, supaya laporan dan alur
 * kasir bisa dicoba dengan angka yang masuk akal.
 *
 * Sifatnya hanya MENAMBAH. Tidak ada data yang dihapus atau diubah, dan perintah
 * menolak jalan bila bisnis sudah punya produk atau order (kecuali --force).
 * Semua baris contoh bisa dikenali dari prefix SKU "DM-" / nomor pelanggan 0812-000-xxxx.
 */
class SeedShowcase extends Command
{
    protected $signature   = 'yoka:seed-showcase {business : ID bisnis} {--days=45 : Lama riwayat transaksi (hari)} {--force : Tetap jalan walau bisnis sudah punya data}';
    protected $description = 'Isi bisnis dengan data contoh (menu, stok, resep, riwayat transaksi). Hanya menambah data.';

    /** nama, kategori, harga, modal, bobot populer, resep [bahan => takaran] */
    private const MENU = [
        ['Espresso',               'Kopi',     15000,  4200, 3,  ['Biji Kopi Arabika' => 18]],
        ['Americano',              'Kopi',     20000,  5600, 6,  ['Biji Kopi Arabika' => 18, 'Es Batu' => 150]],
        ['Cappuccino',             'Kopi',     25000,  8800, 6,  ['Biji Kopi Arabika' => 18, 'Susu Segar' => 120]],
        ['Caffe Latte',            'Kopi',     26000,  9300, 7,  ['Biji Kopi Arabika' => 18, 'Susu Segar' => 180]],
        ['Es Kopi Susu Aren',      'Kopi',     22000,  7600, 14, ['Biji Kopi Robusta' => 18, 'Susu Segar' => 120, 'Gula Aren Cair' => 20, 'Es Batu' => 150]],
        ['Kopi Tubruk',            'Kopi',     12000,  2900, 4,  ['Biji Kopi Robusta' => 15, 'Gula Pasir' => 10]],
        ['Vietnam Drip',           'Kopi',     24000,  8100, 3,  ['Biji Kopi Robusta' => 20, 'Susu Segar' => 60, 'Es Batu' => 120]],
        ['Matcha Latte',           'Non-Kopi', 28000, 11200, 6,  ['Bubuk Matcha' => 8, 'Susu Segar' => 180, 'Es Batu' => 150]],
        ['Cokelat Dingin',         'Non-Kopi', 24000,  8600, 5,  ['Bubuk Cokelat' => 25, 'Susu Segar' => 150, 'Es Batu' => 150]],
        ['Es Teh Lemon',           'Non-Kopi', 12000,  2400, 8,  ['Teh Celup' => 1, 'Sirup Lemon' => 20, 'Gula Pasir' => 15, 'Es Batu' => 150]],
        ['Jus Alpukat',            'Non-Kopi', 22000,  9400, 4,  ['Alpukat' => 1, 'Susu Segar' => 80, 'Bubuk Cokelat' => 10]],
        ['Air Mineral',            'Non-Kopi',  5000,  2000, 5,  []],
        ['Nasi Goreng Spesial',    'Makanan',  28000, 11400, 6,  ['Beras' => 200, 'Telur' => 1, 'Ayam Potong' => 60]],
        ['Mie Goreng',             'Makanan',  24000,  9300, 4,  ['Mie Kuning' => 150, 'Telur' => 1]],
        ['Ayam Geprek',            'Makanan',  27000, 12100, 5,  ['Beras' => 180, 'Ayam Potong' => 120, 'Tepung' => 30]],
        ['Roti Bakar Cokelat Keju','Makanan',  18000,  6100, 6,  ['Roti Tawar' => 2, 'Cokelat Meses' => 15, 'Keju Parut' => 15]],
        ['Kentang Goreng',         'Snack',    16000,  5400, 6,  ['Kentang Beku' => 150]],
        ['Pisang Goreng',          'Snack',    14000,  4500, 4,  ['Pisang' => 2, 'Tepung' => 25]],
        ['Croissant Butter',       'Snack',    20000,  9100, 3,  []],
        ['Brownies',               'Snack',    18000,  7000, 4,  []],
    ];

    /** nama, satuan, stok minimum, harga rata-rata per satuan */
    private const INGREDIENTS = [
        ['Biji Kopi Arabika', 'gram',  1000, 180],
        ['Biji Kopi Robusta', 'gram',  1200, 120],
        ['Susu Segar',        'ml',    4000, 18],
        ['Gula Aren Cair',    'ml',    1000, 25],
        ['Gula Pasir',        'gram',  1500, 15],
        ['Bubuk Matcha',      'gram',   150, 450],
        ['Bubuk Cokelat',     'gram',   500, 120],
        ['Es Batu',           'gram',  8000, 2],
        ['Sirup Lemon',       'ml',     600, 30],
        ['Teh Celup',         'pcs',     60, 600],
        ['Alpukat',           'pcs',     12, 7000],
        ['Telur',             'pcs',     40, 2500],
        ['Beras',             'gram',  4000, 14],
        ['Mie Kuning',        'gram',  1500, 20],
        ['Ayam Potong',       'gram',  3000, 38],
        ['Roti Tawar',        'pcs',     16, 1500],
        ['Cokelat Meses',     'gram',   300, 80],
        ['Keju Parut',        'gram',   300, 100],
        ['Kentang Beku',      'gram',  2500, 30],
        ['Tepung',            'gram',  1500, 12],
        ['Pisang',            'pcs',     20, 2000],
    ];

    /** Bahan yang sengaja dibuat menipis/habis di akhir, supaya peringatan stok terlihat. [nama => rasio terhadap minimum] */
    private const LOW = ['Bubuk Matcha' => 0.55, 'Telur' => 0.45, 'Alpukat' => 0.8, 'Keju Parut' => 0.0];

    private const CUSTOMERS = ['Dewi Lestari', 'Rizky Pratama', 'Ayu Wulandari', 'Bagas Saputra', 'Intan Permata', 'Fajar Nugroho',
                               'Maya Anggraini', 'Doni Kurniawan', 'Putri Amelia', 'Hendra Wijaya', 'Sinta Maharani', 'Yoga Firmansyah'];

    public function handle(): int
    {
        $biz = Business::find($this->argument('business'));
        if (!$biz) { $this->error('Bisnis tidak ditemukan.'); return self::FAILURE; }

        $existing = DB::table('products')->where('business_id', $biz->id)->count() + DB::table('orders')->where('business_id', $biz->id)->count();
        if ($existing > 0 && !$this->option('force')) {
            $this->error("Bisnis \"{$biz->name}\" sudah punya {$existing} data produk/order. Dibatalkan agar tidak mencampur. Pakai --force bila memang ingin menambah.");
            return self::FAILURE;
        }

        $outlet = Outlet::where('business_id', $biz->id)->orderBy('id')->first();
        $owner  = User::where('business_id', $biz->id)->where('role', 'owner')->orderBy('id')->first();
        if (!$outlet || !$owner) { $this->error('Bisnis butuh minimal satu outlet dan satu owner.'); return self::FAILURE; }

        mt_srand(20261111); // hasil sama tiap dijalankan
        $days = max(7, min(120, (int) $this->option('days')));
        $this->info("Mengisi \"{$biz->name}\" ({$days} hari riwayat)…");

        DB::transaction(function () use ($biz, $outlet, $owner, $days) {
            $now = now();

            // ── Kategori & produk ────────────────────────────────────────────
            $catIds = [];
            $cats = ['Kopi' => '#6b3a2a', 'Non-Kopi' => '#3b82f6', 'Makanan' => '#f59e0b', 'Snack' => '#10b981'];
            $i = 0;
            foreach ($cats as $name => $color) {
                $catIds[$name] = DB::table('product_categories')->insertGetId([
                    'business_id' => $biz->id, 'name' => $name, 'slug' => strtolower(str_replace(' ', '-', $name)), 'color' => $color,
                    'sort_order' => ++$i, 'is_active' => 1, 'send_to_kitchen' => in_array($name, ['Makanan', 'Snack']) ? 1 : 0,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            // ── Bahan baku ───────────────────────────────────────────────────
            $ing = [];   // nama => [id, min, cost, stock]
            foreach (self::INGREDIENTS as [$name, $unit, $min, $cost]) {
                $id = DB::table('ingredients')->insertGetId([
                    'business_id' => $biz->id, 'outlet_id' => $outlet->id, 'name' => $name, 'sku' => 'DM-' . strtoupper(substr(md5($name), 0, 5)),
                    'unit' => $unit, 'current_stock' => 0, 'minimum_stock' => $min, 'average_cost' => $cost, 'is_active' => 1,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $ing[$name] = ['id' => $id, 'min' => $min, 'cost' => $cost, 'stock' => 0.0];
            }

            $products = []; // idx => [id, name, price, cost, weight, recipe]
            $addonIds = [];
            foreach (self::MENU as $n => [$name, $cat, $price, $cost, $weight, $recipe]) {
                $pid = DB::table('products')->insertGetId([
                    'business_id' => $biz->id, 'outlet_id' => null, 'product_category_id' => $catIds[$cat], 'name' => $name,
                    'sku' => sprintf('DM-%03d', $n + 1), 'price' => $price, 'cost_price' => $cost, 'is_active' => 1,
                    'is_stock_tracked' => $recipe ? 1 : 0, 'sort_order' => $n + 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $products[] = ['id' => $pid, 'name' => $name, 'cat' => $cat, 'price' => $price, 'cost' => $cost, 'w' => $weight, 'recipe' => $recipe];

                if ($recipe) {
                    $rid = DB::table('recipes')->insertGetId(['business_id' => $biz->id, 'product_id' => $pid, 'created_at' => $now, 'updated_at' => $now]);
                    foreach ($recipe as $iname => $qty) {
                        DB::table('recipe_items')->insert(['recipe_id' => $rid, 'ingredient_id' => $ing[$iname]['id'], 'qty' => $qty, 'created_at' => $now, 'updated_at' => $now]);
                    }
                }
                if ($cat === 'Kopi') {
                    foreach ([['Extra Shot', 5000], ['Oat Milk', 8000]] as $k => [$an, $ap]) {
                        $addonIds[$pid][] = ['id' => DB::table('product_addons')->insertGetId([
                            'product_id' => $pid, 'name' => $an, 'price' => $ap, 'is_active' => 1, 'sort_order' => $k + 1, 'created_at' => $now, 'updated_at' => $now,
                        ]), 'name' => $an, 'price' => $ap];
                    }
                }
            }

            // ── Pelanggan ────────────────────────────────────────────────────
            $custIds = [];
            foreach (self::CUSTOMERS as $k => $cname) {
                $custIds[] = DB::table('customers')->insertGetId([
                    'business_id' => $biz->id, 'name' => $cname, 'phone' => sprintf('0812-000-%04d', $k + 1), 'is_active' => 1,
                    'total_transactions' => 0, 'total_spending' => 0, 'loyalty_points' => 0, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $custStat = array_fill_keys($custIds, [0, 0.0]);

            // ── Simulasi hari demi hari ──────────────────────────────────────
            $movements = [];
            $stockMove = function (string $iname, string $type, float $qty, Carbon $at, ?int $orderId, string $ref, ?string $notes = null) use (&$ing, &$movements, $biz, $outlet, $owner) {
                $before = $ing[$iname]['stock'];
                $after  = $type === 'in' || ($type === 'adjustment' && $qty > 0) ? $before + abs($qty) : $before - abs($qty);
                $after  = max(0, $after);
                $ing[$iname]['stock'] = $after;
                $movements[] = [
                    'ingredient_id' => $ing[$iname]['id'], 'business_id' => $biz->id, 'outlet_id' => $outlet->id, 'order_id' => $orderId, 'user_id' => $owner->id,
                    'type' => $type, 'qty' => abs($qty), 'stock_before' => $before, 'stock_after' => $after, 'unit_cost' => $ing[$iname]['cost'],
                    'reference' => $ref, 'notes' => $notes, 'created_at' => $at, 'updated_at' => $at,
                ];
            };

            $weights = array_map(fn ($p) => $p['w'], $products);
            $pick = function () use ($products, $weights) {
                $r = mt_rand(1, array_sum($weights)); $acc = 0;
                foreach ($products as $k => $p) { $acc += $weights[$k]; if ($r <= $acc) return $p; }
                return $products[0];
            };
            $hourWeights = [8 => 6, 9 => 9, 10 => 8, 11 => 8, 12 => 13, 13 => 12, 14 => 8, 15 => 7, 16 => 9, 17 => 10, 18 => 11, 19 => 11, 20 => 8, 21 => 3];
            $pickHour = function () use ($hourWeights) {
                $r = mt_rand(1, array_sum($hourWeights)); $acc = 0;
                foreach ($hourWeights as $h => $w) { $acc += $w; if ($r <= $acc) return $h; }
                return 12;
            };

            $stats = ['orders' => 0, 'void' => 0, 'refund' => 0, 'revenue' => 0.0, 'shifts' => 0];
            $lastRestock = [];

            for ($d = $days; $d >= 0; $d--) {
                $day = $now->copy()->subDays($d)->startOfDay();
                $isToday = $d === 0;
                $dow = (int) $day->dayOfWeekIso;                      // 1=Senin
                $base = [1 => 16, 2 => 17, 3 => 18, 4 => 19, 5 => 24, 6 => 31, 7 => 28][$dow];
                $growth = 0.75 + 0.5 * (($days - $d) / $days);        // tren naik pelan
                $count = max(6, (int) round($base * $growth + mt_rand(-4, 5)));

                $open = $day->copy()->setTime(7, 55);
                $closeAt = $day->copy()->setTime(22, 5);
                if ($isToday) {
                    $closeAt = $now->copy()->subMinutes(20);
                    if ($closeAt->lte($day->copy()->setTime(9, 0))) { continue; }  // terlalu pagi, lewati hari ini
                    $count = max(5, (int) round($count * ($closeAt->hour - 7) / 14));
                }

                // Restok: tiap Senin & Kamis, atau saat stok sudah di bawah 2x minimum
                $restockDay = $d === $days || in_array($dow, [1, 4], true);
                foreach ($ing as $iname => $info) {
                    if (isset(self::LOW[$iname]) && $d <= 6) continue; // sengaja tidak direstok mendekati akhir
                    if ($restockDay && ($d === $days || $info['stock'] < $info['min'] * 3.2)) {
                        $target = $info['min'] * (in_array($iname, ['Susu Segar', 'Es Batu']) ? 5 : 4);
                        $buy = max(0, $target - $info['stock']);
                        if ($buy > 0) {
                            $stockMove($iname, 'in', (float) ceil($buy), $day->copy()->setTime(7, 20), null, 'Pembelian ' . $day->format('d/m'), 'Belanja bahan (data contoh)');
                        }
                    }
                }

                $shiftId = DB::table('cashier_shifts')->insertGetId([
                    'business_id' => $biz->id, 'outlet_id' => $outlet->id, 'user_id' => $owner->id, 'opening_cash' => 300000,
                    'closing_cash_expected' => 0, 'closing_cash_actual' => 0, 'cash_difference' => 0,
                    'opened_at' => $open, 'closed_at' => null, 'status' => 'open', 'created_at' => $open, 'updated_at' => $open,
                ]);

                // Waktu order: acak di jam sibuk, urut naik
                $times = [];
                for ($o = 0; $o < $count; $o++) {
                    $t = $day->copy()->setTime($pickHour(), mt_rand(0, 59), mt_rand(0, 59));
                    if ($t->lt($open->copy()->addMinutes(10))) $t = $open->copy()->addMinutes(10 + $o);
                    if ($t->gte($closeAt)) $t = $closeAt->copy()->subMinutes(mt_rand(1, 90));
                    $times[] = $t;
                }
                usort($times, fn ($a, $b) => $a->timestamp <=> $b->timestamp);

                $cashTaken = 0.0;
                $seq = 0;
                foreach ($times as $at) {
                    $seq++;
                    $orderNo = 'ORD-' . $day->format('ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

                    // isi order
                    $lines = [];
                    $nItems = mt_rand(1, 100) <= 55 ? 1 : (mt_rand(1, 100) <= 70 ? 2 : mt_rand(3, 4));
                    for ($li = 0; $li < $nItems; $li++) {
                        $p = $pick();
                        $qty = mt_rand(1, 100) <= 82 ? 1 : 2;
                        $adds = [];
                        if (isset($addonIds[$p['id']]) && mt_rand(1, 100) <= 16) { $adds[] = $addonIds[$p['id']][mt_rand(0, 1)]; }
                        $unit = $p['price'] + array_sum(array_column($adds, 'price'));
                        $lines[] = ['p' => $p, 'qty' => $qty, 'adds' => $adds, 'sub' => $unit * $qty];
                    }
                    $subtotal = array_sum(array_column($lines, 'sub'));
                    $discount = mt_rand(1, 100) <= 9 ? (int) round($subtotal * 0.1 / 500) * 500 : 0;
                    $grand = $subtotal - $discount;

                    // status
                    $roll = mt_rand(1, 100);
                    $status = $roll <= 3 ? 'cancelled' : ($roll <= 6 ? 'refunded' : 'paid');

                    // pembayaran
                    $method = ['cash', 'cash', 'cash', 'cash', 'qris', 'qris', 'qris', 'qris', 'debit', 'ewallet', 'transfer'][mt_rand(0, 10)];
                    $split  = $status === 'paid' && $grand >= 40000 && mt_rand(1, 100) <= 6;
                    $paid = $grand; $change = 0;
                    if ($method === 'cash' && !$split) {
                        $paid = (int) (ceil($grand / 5000) * 5000);
                        if (mt_rand(1, 100) <= 35) $paid = (int) (ceil($grand / 50000) * 50000);
                        $change = $paid - $grand;
                    }

                    $custId = mt_rand(1, 100) <= 30 ? $custIds[mt_rand(0, count($custIds) - 1)] : null;
                    $type = mt_rand(1, 100) <= 58 ? 'dine_in' : 'takeaway';

                    $orderId = DB::table('orders')->insertGetId([
                        'business_id' => $biz->id, 'outlet_id' => $outlet->id, 'user_id' => $owner->id, 'cashier_shift_id' => $shiftId,
                        'customer_id' => $custId, 'order_number' => $orderNo, 'order_type' => $type, 'delivery_fee' => 0,
                        'subtotal' => $subtotal, 'discount_amount' => $discount, 'tax_amount' => 0, 'service_amount' => 0,
                        'grand_total' => $grand, 'paid_amount' => $paid, 'change_amount' => $change,
                        'payment_method' => $split ? 'cash' : $method,
                        'payment_status' => $status === 'paid' ? 'paid' : ($status === 'refunded' ? 'refunded' : 'unpaid'),
                        'status' => $status, 'kitchen_status' => 'completed', 'is_split_payment' => $split ? 1 : 0,
                        'cancel_reason' => $status === 'cancelled' ? ['Pelanggan batal pesan', 'Salah input menu', 'Pesanan dobel'][mt_rand(0, 2)] : null,
                        'refund_reason' => $status === 'refunded' ? ['Minuman tumpah', 'Pesanan tidak sesuai', 'Pelanggan komplain rasa'][mt_rand(0, 2)] : null,
                        'refunded_at' => $status === 'refunded' ? $at->copy()->addMinutes(mt_rand(5, 40)) : null,
                        'table_number' => $type === 'dine_in' ? (string) mt_rand(1, 12) : null,
                        'ordered_at' => $at, 'created_at' => $at, 'updated_at' => $at,
                    ]);

                    foreach ($lines as $ln) {
                        $itemId = DB::table('order_items')->insertGetId([
                            'order_id' => $orderId, 'product_id' => $ln['p']['id'], 'product_name' => $ln['p']['name'],
                            'price' => $ln['p']['price'], 'cost_price' => $ln['p']['cost'], 'qty' => $ln['qty'], 'subtotal' => $ln['sub'],
                            'kitchen_status' => 'completed', 'created_at' => $at, 'updated_at' => $at,
                        ]);
                        foreach ($ln['adds'] as $ad) {
                            DB::table('order_item_addons')->insert([
                                'order_item_id' => $itemId, 'product_addon_id' => $ad['id'], 'addon_name' => $ad['name'], 'price' => $ad['price'],
                                'created_at' => $at, 'updated_at' => $at,
                            ]);
                        }
                        // stok berkurang hanya untuk order yang jadi (void/refund dianggap dikembalikan)
                        if ($status === 'paid') {
                            foreach ($ln['p']['recipe'] as $iname => $q) {
                                $stockMove($iname, 'sale', $q * $ln['qty'], $at, $orderId, $orderNo);
                            }
                        }
                    }

                    if ($split) {
                        $cashPart = (int) (floor($grand * 0.4 / 1000) * 1000);
                        DB::table('order_payments')->insert([
                            ['order_id' => $orderId, 'payment_method' => 'cash', 'amount' => $cashPart, 'created_at' => $at, 'updated_at' => $at],
                            ['order_id' => $orderId, 'payment_method' => 'qris', 'amount' => $grand - $cashPart, 'created_at' => $at, 'updated_at' => $at],
                        ]);
                        $cashTaken += $cashPart;
                    } elseif ($status === 'paid' && $method === 'cash') {
                        $cashTaken += $grand;
                    }

                    if ($status === 'paid') {
                        $stats['orders']++; $stats['revenue'] += $grand;
                        if ($custId) { $custStat[$custId][0]++; $custStat[$custId][1] += $grand; }
                    } else { $stats[$status === 'cancelled' ? 'void' : 'refund']++; }
                }

                // tutup shift
                $expected = 300000 + $cashTaken;
                $diff = [0, 0, 0, 0, -2000, 1000, -5000, 0][mt_rand(0, 7)];
                DB::table('cashier_shifts')->where('id', $shiftId)->update([
                    'closing_cash_expected' => $expected, 'closing_cash_actual' => $expected + $diff, 'cash_difference' => $diff,
                    'closed_at' => $closeAt, 'status' => 'closed', 'updated_at' => $closeAt,
                    'notes' => $diff !== 0 ? 'Selisih kecil, sudah dicek ulang.' : null,
                ]);
                $stats['shifts']++;
            }

            // ── Penutup: sedikit pembuangan (waste), lalu stok opname membuat beberapa bahan menipis ─
            // Keduanya bertanda waktu paling akhir supaya riwayat stok tetap berurutan dan saldonya nyambung.
            foreach (['Susu Segar' => 400, 'Alpukat' => 1, 'Roti Tawar' => 2] as $iname => $q) {
                if ($ing[$iname]['stock'] > $q + $ing[$iname]['min']) {
                    $stockMove($iname, 'waste', $q, $now->copy()->subMinutes(10), null, 'Waste', 'Kedaluwarsa/rusak (data contoh)');
                }
            }
            $end = $now->copy()->subMinutes(5);
            foreach (self::LOW as $iname => $ratio) {
                $delta = $ing[$iname]['min'] * $ratio - $ing[$iname]['stock'];
                if (abs($delta) >= 1) {
                    $stockMove($iname, 'adjustment', $delta, $end, null, 'Stok opname', $ratio == 0.0 ? 'Habis, belum sempat belanja (data contoh)' : 'Hasil hitung ulang fisik (data contoh)');
                }
            }

            // Simpan movements urut waktu (supaya riwayat stok rapi) lalu stok akhir bahan
            usort($movements, fn ($a, $b) => $a['created_at']->timestamp <=> $b['created_at']->timestamp);
            foreach (array_chunk($movements, 500) as $chunk) {
                DB::table('stock_movements')->insert($chunk);
            }
            foreach ($ing as $info) {
                DB::table('ingredients')->where('id', $info['id'])->update(['current_stock' => $info['stock'], 'updated_at' => $now]);
            }
            foreach ($custStat as $cid => [$n, $sum]) {
                DB::table('customers')->where('id', $cid)->update(['total_transactions' => $n, 'total_spending' => $sum]);
            }

            $settings = (array) $biz->settings;
            $settings['showcase_seeded_at'] = $now->toIso8601String();
            $biz->update(['settings' => $settings]);

            $this->table(['Item', 'Jumlah'], [
                ['Kategori', count($catIds)], ['Produk', count($products)], ['Bahan baku', count($ing)], ['Pelanggan', count($custIds)],
                ['Shift', $stats['shifts']], ['Order lunas', $stats['orders']], ['Order void', $stats['void']], ['Order refund', $stats['refund']],
                ['Omzet total', 'Rp ' . number_format($stats['revenue'], 0, ',', '.')], ['Mutasi stok', count($movements)],
            ]);
        });

        $this->info('Selesai.');
        return self::SUCCESS;
    }
}
