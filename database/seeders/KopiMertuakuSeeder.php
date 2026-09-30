<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Live tenant for KOPI MERTUAKU — real menu, real staff, no sample sales.
 *
 * Unlike the demo seeders this one never wipes anything: once the shop starts
 * selling, a re-run would destroy real transactions. If the business already
 * exists it stops and changes nothing.
 *
 *   php artisan db:seed --class=KopiMertuakuSeeder --force
 */
class KopiMertuakuSeeder extends Seeder
{
    private const BUSINESS_SLUG = 'kopi-mertuaku';

    /**
     * Credentials come from the server's .env so they never land in git:
     * KM_PASSWORD, KM_PIN_ADMIN, KM_PIN_KASIR1, KM_PIN_KASIR2.
     */
    private function secret(string $key): string
    {
        $value = (string) env($key, '');

        if ($value === '') {
            throw new \RuntimeException("{$key} belum diisi di .env — seeder dibatalkan.");
        }

        return $value;
    }

    public function run(): void
    {
        if (Business::where('slug', self::BUSINESS_SLUG)->exists()) {
            $this->command?->warn('KOPI MERTUAKU sudah ada — seeder dilewati, tidak ada data yang diubah.');

            return;
        }

        // Fail before creating anything if a credential is missing.
        foreach (['KM_PASSWORD', 'KM_PIN_ADMIN', 'KM_PIN_KASIR1', 'KM_PIN_KASIR2'] as $key) {
            $this->secret($key);
        }

        $business = $this->business();
        $outlet   = $this->outlet($business);

        $this->users($business, $outlet);
        $this->products($business, $outlet, $this->categories($business));

        $this->command?->info('');
        $this->command?->info('  KOPI MERTUAKU siap.');
        $this->command?->info('  Login admin : admin@kopimertuaku.com');
        $this->command?->info('  Tablet kasir — buka /kasir, kode outlet KM01');
        $this->command?->info('  Sandi & PIN sesuai KM_* di .env');
        $this->command?->info('');
    }

    private function business(): Business
    {
        $business = Business::create([
            'name'     => 'KOPI MERTUAKU',
            'slug'     => self::BUSINESS_SLUG,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'settings' => [
                'receipt_header'       => 'KOPI MERTUAKU',
                'receipt_footer'       => 'Terima kasih! Follow IG @kopi_mertuaku',
                'receipt_size'         => '58mm',
                // Blueprint BP-ECO58D paired to a Windows laptop, printed from Chrome.
                'print_method'         => 'browser',
                // Menu prices are final prices, so no tax or service on top.
                'enable_tax'           => false,
                'tax_percent'          => 0,
                'enable_service'       => false,
                'service_percent'      => 0,
                'allow_negative_stock' => false,
                'points_per_rupiah'    => 0.001,
                'point_value_rupiah'   => 100,
                'enable_wa_receipt'    => false,
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
            ]);
        }

        return $business;
    }

    private function outlet(Business $business): Outlet
    {
        return Outlet::create([
            'business_id' => $business->id,
            'name'        => 'KOPI MERTUAKU',
            'code'        => 'KM01',
            'is_active'   => true,
        ]);
    }

    private function users(Business $business, Outlet $outlet): void
    {
        $make = fn (string $name, string $email, string $role, string $pinKey) => User::create([
            'business_id'       => $business->id,
            'outlet_id'         => $outlet->id,
            'name'              => $name,
            'email'             => $email,
            'email_verified_at' => now(),
            'password'          => Hash::make($this->secret('KM_PASSWORD')),
            'pin'               => Hash::make($this->secret($pinKey)),
            'role'              => $role,
            'is_active'         => true,
        ]);

        $make('Diki', 'admin@kopimertuaku.com', 'admin', 'KM_PIN_ADMIN');
        $make('Kasir 1', 'kasir1@kopimertuaku.com', 'cashier', 'KM_PIN_KASIR1');
        $make('Kasir 2', 'kasir2@kopimertuaku.com', 'cashier', 'KM_PIN_KASIR2');
    }

    /** @return array<string, ProductCategory> */
    private function categories(Business $business): array
    {
        $rows = [
            'minuman' => ['Minuman', '#8B5E3C', '☕', 1],
            'air'     => ['Air Mineral', '#3B82F6', '💧', 2],
            'mie'     => ['Mie', '#EF4444', '🍜', 3],
            'snack'   => ['Snack & Gorengan', '#F59E0B', '🍟', 4],
            'es'      => ['Ice Cream', '#EC4899', '🍦', 5],
        ];

        $out = [];

        foreach ($rows as $key => [$name, $color, $icon, $sort]) {
            $out[$key] = ProductCategory::create([
                'business_id' => $business->id,
                'name'        => $name,
                'slug'        => Str::slug($name),
                'color'       => $color,
                'icon'        => $icon,
                'sort_order'  => $sort,
                'is_active'   => true,
            ]);
        }

        return $out;
    }

    /** @param array<string, ProductCategory> $cats */
    private function products(Business $business, Outlet $outlet, array $cats): void
    {
        // Several items on the list share a name and differ only by price, so
        // the price goes into the name to keep them apart on the register.
        $rows = [
            ['minuman', 'Espresso', 25000],
            ['minuman', 'Red Velvet', 25000],
            ['minuman', 'Teh Tarik', 25000],
            ['minuman', 'Ice Tea', 25000],
            ['minuman', 'Matcha', 25000],
            ['minuman', 'Thai Tea', 25000],
            ['minuman', 'Ice Lemon Tea', 25000],
            ['minuman', 'Ice Taro', 25000],
            ['minuman', 'Kopi Gula Aren', 25000],
            ['minuman', 'Chocolate', 25000],

            ['air', 'Air Mineral 600ml', 5000],
            ['air', 'Air Mineral 1.5L', 10000],

            ['snack', 'Popcorn Caramel', 10000],
            ['snack', 'Popcorn Original', 7000],

            ['mie', 'Aneka Mie 6.5K', 6500],
            ['mie', 'Aneka Mie 10K', 10000],
            ['mie', 'Pop Mie', 10000],

            ['es', 'Ice Cream 2K', 2000],
            ['es', 'Ice Cream 3K', 3000],
            ['es', 'Ice Cream 5K', 5000],

            ['snack', 'Gorengan 1.5K', 1500],
            ['snack', 'Gorengan 3K', 3000],
            ['snack', 'Jasuke', 7000],
            ['snack', 'Kentang Goreng', 10000],
            ['snack', 'Basreng', 5000],
            ['snack', 'Sosis', 5000],
            ['snack', 'Snack Gorengan', 8000],
        ];

        foreach ($rows as $i => [$cat, $name, $price]) {
            Product::create([
                'business_id'         => $business->id,
                'outlet_id'           => $outlet->id,
                'product_category_id' => $cats[$cat]->id,
                'name'                => $name,
                'sku'                 => 'KM' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'price'               => $price,
                'cost_price'          => 0,
                'is_active'           => true,
                'is_stock_tracked'    => false,
                'sort_order'          => $i + 1,
            ]);
        }
    }
}
