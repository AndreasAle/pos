<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PosOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo tenant for VARIL RESTO — a full cafe ready to present.
 *
 * Menu photos live in public/images/menu so they ship with the repo and need
 * no storage symlink or external hosting. Sample orders are placed through
 * PosOrderService rather than inserted directly, so stock movements, loyalty
 * points and every report line are real rather than fabricated.
 *
 * Re-runnable: it clears its own tenant first.
 */
class VarilRestoSeeder extends Seeder
{
    private const BUSINESS_SLUG = 'varil-resto';

    private function password(): string
    {
        return env('SEED_PASSWORD', 'varilresto2026');
    }

    public function run(): void
    {
        $this->command?->warn('Menghapus data VARIL RESTO sebelumnya (jika ada)...');

        foreach (Business::where('slug', self::BUSINESS_SLUG)->get() as $old) {
            // Order matters. Deleting the business cascades into outlets, which
            // in turn tries to SET NULL on products.outlet_id for rows the same
            // cascade is deleting — MySQL refuses that. Clearing the leaf tables
            // first keeps the cascade shallow enough to succeed.
            \App\Models\Order::where('business_id', $old->id)->delete();
            \App\Models\StockMovement::where('business_id', $old->id)->delete();
            Product::where('business_id', $old->id)->delete();
            Ingredient::where('business_id', $old->id)->delete();

            $old->delete();
        }

        // users.business_id is nullOnDelete, so the accounts outlive the business
        // and their unique emails would collide on a re-run.
        User::whereIn('email', [
            'owner@varilresto.com',
            'admin@varilresto.com',
            'kasir@varilresto.com',
            'dapur@varilresto.com',
        ])->delete();

        $business = $this->business();
        $outlet   = $this->outlet($business);
        $users    = $this->users($business, $outlet);
        $cats     = $this->categories($business);
        $products = $this->products($business, $outlet, $cats);

        $this->recipes($business, $outlet, $products);
        $this->customers($business);
        $this->promotions($business);
        $this->sampleOrders($business, $outlet, $users['cashier'], $products);

        $this->command?->info('');
        $this->command?->info('  VARIL RESTO siap.');
        $this->command?->info('  Login  : owner@varilresto.com');
        $this->command?->info('  Sandi  : ' . $this->password());
        $this->command?->info('');
    }

    private function business(): Business
    {
        $business = Business::create([
            'name'     => 'VARIL RESTO',
            'slug'     => self::BUSINESS_SLUG,
            'phone'    => '0811-2345-6789',
            'email'    => 'halo@varilresto.com',
            'address'  => 'Jl. Lintas Sumatera KM 3,5, Palembang',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'settings' => [
                'receipt_header'       => 'VARIL RESTO',
                'receipt_footer'       => 'Terima kasih sudah mampir! Sampai jumpa lagi 🙌',
                'receipt_size'         => '80mm',
                'enable_tax'           => true,
                'tax_percent'          => 10,
                'enable_service'       => false,
                'service_percent'      => 0,
                'allow_negative_stock' => false,
                'points_per_rupiah'    => 0.001,   // 1 poin per Rp 1.000
                'point_value_rupiah'   => 100,     // 1 poin = Rp 100
                'enable_wa_receipt'    => false,
                // Static QRIS from the merchant's DANA business account. The POS
                // turns this into a dynamic QR with the exact amount at checkout.
                'qris_payload'         => '00020101021126570011ID.DANA.WWW011893600915303371673602090337167360303UMI51440014ID.CO.QRIS.WWW0215ID10265623758930303UMI5204737253033605802ID5906Conweb6014Kota Palembang61053016163045C12',
            ],
        ]);

        $plan = SubscriptionPlan::where('slug', 'business')->first()
            ?? SubscriptionPlan::where('slug', 'free')->first()
            ?? SubscriptionPlan::first();

        if ($plan) {
            BusinessSubscription::create([
                'business_id'          => $business->id,
                'subscription_plan_id' => $plan->id,
                'starts_at'            => today(),
                'ends_at'              => today()->addYear(),
                'status'               => 'active',
                'paid_at'              => now(),
                'notes'                => 'Demo tenant',
            ]);
        }

        return $business;
    }

    private function outlet(Business $business): Outlet
    {
        return Outlet::create([
            'business_id' => $business->id,
            'name'        => 'VARIL RESTO — Pusat',
            'code'        => 'VR01',
            'phone'       => '0811-2345-6789',
            'address'     => 'Jl. Lintas Sumatera KM 3,5, Palembang',
            'is_active'   => true,
        ]);
    }

    /** @return array<string, User> */
    private function users(Business $business, Outlet $outlet): array
    {
        $make = fn (string $name, string $email, string $role) => User::create([
            'business_id'       => $business->id,
            'outlet_id'         => $outlet->id,
            'name'              => $name,
            'email'             => $email,
            'email_verified_at' => now(),
            'password'          => Hash::make($this->password()),
            'role'              => $role,
            'is_active'         => true,
        ]);

        return [
            'owner'   => $make('Varil Wijaya', 'owner@varilresto.com', 'owner'),
            'admin'   => $make('Rina Admin', 'admin@varilresto.com', 'admin'),
            'cashier' => $make('Dewi Kasir', 'kasir@varilresto.com', 'cashier'),
            'kitchen' => $make('Agus Dapur', 'dapur@varilresto.com', 'kitchen'),
        ];
    }

    /** @return array<string, ProductCategory> */
    private function categories(Business $business): array
    {
        $rows = [
            'kopi'    => ['Kopi', '#8B5E3C', '☕', 1],
            'nonkopi' => ['Non-Kopi', '#10B981', '🍹', 2],
            'makanan' => ['Makanan Utama', '#EF4444', '🍽️', 3],
            'snack'   => ['Snack & Dessert', '#F59E0B', '🍰', 4],
        ];

        $out = [];

        foreach ($rows as $key => [$name, $color, $icon, $sort]) {
            $out[$key] = ProductCategory::create([
                'business_id' => $business->id,
                'name'        => $name,
                'slug'        => \Illuminate\Support\Str::slug($name),
                'color'       => $color,
                'icon'        => $icon,
                'sort_order'  => $sort,
                'is_active'   => true,
            ]);
        }

        return $out;
    }

    /**
     * @param  array<string, ProductCategory>  $cats
     * @return array<string, Product>
     */
    private function products(Business $business, Outlet $outlet, array $cats): array
    {
        // key, category, name, price, cost, image, description
        $rows = [
            ['americano', 'kopi', 'Americano', 22000, 7000, 'americano', 'Espresso ganda dengan air panas. Pahit bersih, cocok untuk menemani kerja.'],
            ['cappuccino', 'kopi', 'Cappuccino', 28000, 9000, 'cappuccino', 'Espresso dengan steamed milk dan foam tebal, ditutup latte art.'],
            ['eskopsus', 'kopi', 'Es Kopi Susu Gula Aren', 25000, 8000, 'es-kopi-susu', 'Favorit sejuta umat. Espresso, susu segar, dan gula aren asli.'],
            ['eslatte', 'kopi', 'Es Latte', 30000, 10000, 'es-latte', 'Latte dingin dengan es batu, lembut dan tidak terlalu manis.'],

            ['matcha', 'nonkopi', 'Matcha Latte', 32000, 12000, 'matcha-latte', 'Matcha premium Jepang dikocok halus dengan susu segar.'],
            ['tehtarik', 'nonkopi', 'Teh Tarik', 20000, 6000, 'teh-tarik', 'Teh susu ditarik hingga berbusa, disajikan hangat.'],
            ['jusjeruk', 'nonkopi', 'Jus Jeruk Peras', 24000, 9000, 'jus-jeruk', 'Jeruk peras asli tanpa gula tambahan, segar dan tinggi vitamin C.'],

            ['nasgor', 'makanan', 'Nasi Goreng Varil', 38000, 15000, 'nasi-goreng', 'Nasi goreng spesial ala rumah dengan telur, ayam suwir, dan acar.'],
            ['miegor', 'makanan', 'Mie Goreng Spesial', 35000, 13000, 'mie-goreng', 'Mie goreng bumbu wangi dengan sayuran segar dan telur.'],
            ['burger', 'makanan', 'Beef Burger', 45000, 20000, 'beef-burger', 'Patty daging sapi 100 gram, keju leleh, selada, dan saus rahasia.'],
            ['pizza', 'makanan', 'Pizza Margherita', 65000, 25000, 'pizza', 'Pizza tipis dengan saus tomat, mozzarella, dan basil segar.'],

            ['kentang', 'snack', 'Kentang Goreng', 25000, 8000, 'kentang-goreng', 'Kentang goreng renyah ditaburi keju parut dan parsley.'],
            ['croissant', 'snack', 'Croissant Butter', 22000, 8000, 'croissant', 'Croissant berlapis mentega, renyah di luar lembut di dalam.'],
            ['rotibakar', 'snack', 'Roti Bakar Coklat Keju', 26000, 9000, 'roti-bakar', 'Roti panggang isi coklat dan keju, disajikan dengan saus pendamping.'],
            ['pancake', 'snack', 'Pancake Madu', 35000, 12000, 'pancake', 'Tumpukan pancake lembut dengan madu asli dan potongan pisang.'],
            ['cake', 'snack', 'Chocolate Cake', 32000, 13000, 'chocolate-cake', 'Kue coklat berlapis dengan ganache dan buttercream.'],
        ];

        $out = [];

        foreach ($rows as $i => [$key, $cat, $name, $price, $cost, $image, $desc]) {
            $out[$key] = Product::create([
                'business_id'         => $business->id,
                'outlet_id'           => $outlet->id,
                'product_category_id' => $cats[$cat]->id,
                'name'                => $name,
                'sku'                 => 'VR' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'description'         => $desc,
                'image'               => 'images/menu/' . $image . '.jpg',
                'price'               => $price,
                'cost_price'          => $cost,
                'is_active'           => true,
                'is_stock_tracked'    => false,
                'sort_order'          => $i + 1,
            ]);
        }

        $this->variantsAndAddons($out);

        return $out;
    }

    /** @param array<string, Product> $p */
    private function variantsAndAddons(array $p): void
    {
        $variant = fn (Product $product, string $name, float $adj, int $sort) => ProductVariant::create([
            'product_id'       => $product->id,
            'name'             => $name,
            'price_adjustment' => $adj,
            'is_active'        => true,
            'sort_order'       => $sort,
        ]);

        $addon = fn (Product $product, string $name, float $price, int $sort) => ProductAddon::create([
            'product_id' => $product->id,
            'name'       => $name,
            'price'      => $price,
            'is_active'  => true,
            'sort_order' => $sort,
        ]);

        foreach (['americano', 'cappuccino', 'matcha'] as $key) {
            $variant($p[$key], 'Regular', 0, 1);
            $variant($p[$key], 'Large', 6000, 2);
            $addon($p[$key], 'Extra Shot', 8000, 1);
            $addon($p[$key], 'Oat Milk', 6000, 2);
        }

        $variant($p['eskopsus'], 'Regular', 0, 1);
        $variant($p['eskopsus'], 'Large', 6000, 2);
        $addon($p['eskopsus'], 'Extra Shot', 8000, 1);
        $addon($p['eskopsus'], 'Less Sugar', 0, 2);

        foreach (['nasgor', 'miegor'] as $key) {
            $variant($p[$key], 'Porsi Biasa', 0, 1);
            $variant($p[$key], 'Porsi Jumbo', 12000, 2);
            $addon($p[$key], 'Telur Mata Sapi', 7000, 1);
            $addon($p[$key], 'Ayam Suwir', 10000, 2);
            $addon($p[$key], 'Kerupuk', 3000, 3);
        }

        $addon($p['burger'], 'Extra Cheese', 8000, 1);
        $addon($p['burger'], 'Extra Patty', 20000, 2);
        $addon($p['kentang'], 'Saus Keju', 6000, 1);
        $addon($p['pizza'], 'Extra Mozzarella', 12000, 1);
    }

    /** @param array<string, Product> $products */
    private function recipes(Business $business, Outlet $outlet, array $products): void
    {
        $ingredient = fn (string $name, string $unit, float $stock, float $min, float $cost) => Ingredient::create([
            'business_id'   => $business->id,
            'outlet_id'     => $outlet->id,
            'name'          => $name,
            'sku'           => 'BHN-' . strtoupper(substr(md5($name), 0, 5)),
            'unit'          => $unit,
            'current_stock' => $stock,
            'minimum_stock' => $min,
            'average_cost'  => $cost,
            'is_active'     => true,
        ]);

        $biji   = $ingredient('Biji Kopi Arabika', 'gram', 5000, 1000, 180);
        $susu   = $ingredient('Susu Full Cream', 'ml', 20000, 4000, 18);
        $aren   = $ingredient('Gula Aren Cair', 'ml', 4000, 800, 35);
        $bubuk  = $ingredient('Bubuk Matcha', 'gram', 800, 200, 900);
        $beras  = $ingredient('Beras', 'gram', 30000, 5000, 14);
        $telur  = $ingredient('Telur Ayam', 'pcs', 200, 40, 2500);
        // Deliberately below its minimum so the low-stock report has something
        // to show during the demo.
        $ingredient('Keju Mozzarella', 'gram', 250, 1000, 160);

        $recipe = function (Product $product, array $items) use ($business) {
            $product->update(['is_stock_tracked' => true]);

            $r = Recipe::create([
                'product_id'  => $product->id,
                'business_id' => $business->id,
            ]);

            foreach ($items as [$ing, $qty]) {
                RecipeItem::create([
                    'recipe_id'     => $r->id,
                    'ingredient_id' => $ing->id,
                    'qty'           => $qty,
                ]);
            }
        };

        $recipe($products['americano'], [[$biji, 18]]);
        $recipe($products['cappuccino'], [[$biji, 18], [$susu, 150]]);
        $recipe($products['eskopsus'], [[$biji, 18], [$susu, 120], [$aren, 30]]);
        $recipe($products['eslatte'], [[$biji, 18], [$susu, 180]]);
        $recipe($products['matcha'], [[$bubuk, 8], [$susu, 200]]);
        $recipe($products['nasgor'], [[$beras, 200], [$telur, 1]]);
        $recipe($products['miegor'], [[$telur, 1]]);
    }

    private function customers(Business $business): void
    {
        $rows = [
            ['Budi Santoso', '081234567801', 'budi@mail.com', 1250],
            ['Siti Rahayu', '081234567802', 'siti@mail.com', 480],
            ['Andi Pratama', '081234567803', null, 120],
            ['Maya Lestari', '081234567804', 'maya@mail.com', 0],
        ];

        foreach ($rows as [$name, $phone, $email, $points]) {
            Customer::create([
                'business_id'    => $business->id,
                'name'           => $name,
                'phone'          => $phone,
                'email'          => $email,
                'loyalty_points' => $points,
                'is_active'      => true,
            ]);
        }
    }

    private function promotions(Business $business): void
    {
        Promotion::create([
            'business_id' => $business->id,
            'name'        => 'Diskon Pembukaan 10%',
            'code'        => 'GRANDOPEN',
            'type'        => 'percent',
            'value'       => 10,
            'min_order'   => 50000,
            'starts_at'   => today()->subDays(30),
            'ends_at'     => today()->addDays(60),
            'is_active'   => true,
        ]);

        Promotion::create([
            'business_id' => $business->id,
            'name'        => 'Potongan Rp 15.000',
            'code'        => 'HEMAT15',
            'type'        => 'nominal',
            'value'       => 15000,
            'min_order'   => 100000,
            'starts_at'   => today()->subDays(7),
            'ends_at'     => today()->addDays(30),
            'is_active'   => true,
        ]);
    }

    /**
     * Place real sales through the POS service so dashboards, reports and stock
     * movements all line up with each other.
     *
     * @param  array<string, Product>  $products
     */
    private function sampleOrders(Business $business, Outlet $outlet, User $cashier, array $products): void
    {
        $service = app(PosOrderService::class);
        $keys    = array_keys($products);
        $methods = ['cash', 'cash', 'cash', 'qris', 'qris', 'transfer'];
        $placed  = 0;

        for ($daysAgo = 13; $daysAgo >= 0; $daysAgo--) {
            $shift = CashierShift::create([
                'business_id'  => $business->id,
                'outlet_id'    => $outlet->id,
                'user_id'      => $cashier->id,
                'opening_cash' => 200000,
                'opened_at'    => now()->subDays($daysAgo)->setTime(8, 0),
                'status'       => 'open',
            ]);

            // Busier on weekends so the sales chart has a believable shape.
            $date   = now()->subDays($daysAgo);
            $orders = $date->isWeekend() ? random_int(6, 9) : random_int(3, 6);

            for ($i = 0; $i < $orders; $i++) {
                $items   = [];
                $picked  = $keys;
                shuffle($picked);

                foreach (array_slice($picked, 0, random_int(1, 3)) as $key) {
                    $items[] = [
                        'product_id' => $products[$key]->id,
                        'qty'        => random_int(1, 2),
                    ];
                }

                $order = $service->createOrder($cashier, [
                    'items'          => $items,
                    'payment_method' => $methods[array_rand($methods)],
                    'paid_amount'    => 1000000,
                    'order_type'     => random_int(1, 10) > 8 ? 'takeaway' : 'dine_in',
                ]);

                // Backdate so the reports span a fortnight.
                $order->forceFill([
                    'created_at' => $date->copy()->setTime(random_int(9, 20), random_int(0, 59)),
                    'ordered_at' => $date->copy()->setTime(random_int(9, 20), random_int(0, 59)),
                ])->saveQuietly();

                $placed++;
            }

            // Close every past shift; leave today's open so the cashier can sell.
            if ($daysAgo > 0) {
                $expected = 200000 + (float) $shift->orders()
                    ->where('status', 'paid')
                    ->where('payment_method', 'cash')
                    ->sum('grand_total');

                $shift->update([
                    'closing_cash_expected' => $expected,
                    'closing_cash_actual'   => $expected,
                    'cash_difference'       => 0,
                    'closed_at'             => now()->subDays($daysAgo)->setTime(22, 0),
                    'status'                => 'closed',
                ]);
            }
        }

        $this->command?->info("  {$placed} transaksi contoh dibuat selama 14 hari terakhir.");
    }
}
