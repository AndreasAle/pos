<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Live tenant for CHARYNE.SHOP — a clothing, bag and tumbler shop.
 *
 * Retail, not F&B: no dine-in, kitchen or recipes. Sizes are product
 * variants. Shirts come in S–XXL at one price; bags and tumblers cost more as
 * they get bigger, so their sizes carry a price adjustment over the smallest.
 *
 * Never wipes anything; if the shop already exists it stops.
 *
 *   php artisan db:seed --class=CharyneShopSeeder --force
 */
class CharyneShopSeeder extends Seeder
{
    private const BUSINESS_SLUG = 'charyne-shop';

    /** Sizes for clothing, all at the listed price. */
    private const APPAREL_SIZES = ['S', 'M', 'L', 'XL', 'XXL'];

    /**
     * Credentials come from the server's .env so they never land in git:
     * CH_PASSWORD, CH_PIN_ADMIN, CH_PIN_KASIR1.
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
            $this->command?->warn('CHARYNE.SHOP sudah ada — seeder dilewati, tidak ada data yang diubah.');

            return;
        }

        // Fail before creating anything if a credential is missing.
        foreach (['CH_PASSWORD', 'CH_PIN_ADMIN', 'CH_PIN_KASIR1'] as $key) {
            $this->secret($key);
        }

        $business = $this->business();
        $outlet   = $this->outlet($business);

        $this->users($business, $outlet);
        $this->catalogue($business, $outlet);

        $this->command?->info('');
        $this->command?->info('  CHARYNE.SHOP siap.');
        $this->command?->info('  Login admin : admin@charyne.shop');
        $this->command?->info('  Tablet kasir — buka /kasir, kode outlet CH01');
        $this->command?->info('  Sandi & PIN sesuai CH_* di .env');
        $this->command?->info('');
    }

    private function business(): Business
    {
        $business = Business::create([
            'name'     => 'charyne.shop',
            'slug'     => self::BUSINESS_SLUG,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'settings' => [
                'business_type'        => 'retail',
                'receipt_header'       => 'charyne.shop',
                'receipt_footer'       => 'Terima kasih sudah belanja di charyne.shop!',
                'receipt_size'         => '58mm',
                'print_method'         => 'browser',
                'receipt_show_order_type' => true,
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
            'name'        => 'charyne.shop',
            'code'        => 'CH01',
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
            'password'          => Hash::make($this->secret('CH_PASSWORD')),
            'pin'               => Hash::make($this->secret($pinKey)),
            'role'              => $role,
            'is_active'         => true,
        ]);

        $make('Admin Charyne', 'admin@charyne.shop', 'admin', 'CH_PIN_ADMIN');
        $make('Kasir 1', 'kasir1@charyne.shop', 'cashier', 'CH_PIN_KASIR1');
    }

    /**
     * category => [product name, base price, sizes]
     * sizes: null for apparel (S–XXL, same price), or [size => price].
     */
    private function rows(): array
    {
        return [
            'Kaos Lengan Pendek' => [
                ['Kaos 30s Lengan Pendek', 70000, null],
                ['Kaos 24s Lengan Pendek', 80000, null],
                ['Kaos 20s Lengan Pendek', 100000, null],
            ],
            'Kaos Lengan Panjang' => [
                ['Kaos 30s Lengan Panjang', 80000, null],
                ['Kaos 24s Lengan Panjang', 90000, null],
                ['Kaos 20s Lengan Panjang', 110000, null],
                ['Kaos 16s Lengan Panjang', 160000, null],
            ],
            'Kaos Berkerah Lengan Pendek' => [
                ['Kaos Berkerah PE Lengan Pendek', 100000, null],
                ['Kaos Berkerah CVC Lengan Pendek', 160000, null],
            ],
            'Kaos Berkerah Lengan Panjang' => [
                ['Kaos Berkerah PE Lengan Panjang', 110000, null],
                ['Kaos Berkerah CVC Lengan Panjang', 170000, null],
            ],
            'Kemeja Lengan Pendek' => [
                ['Kemeja Standar AM Lengan Pendek', 160000, null],
                ['Kemeja Standar Tropical Lengan Pendek', 180000, null],
                ['Kemeja Tactical AM Lengan Pendek', 200000, null],
                ['Kemeja Tactical Tropical Lengan Pendek', 250000, null],
            ],
            'Kemeja Lengan Panjang' => [
                ['Kemeja Standar AM Lengan Panjang', 170000, null],
                ['Kemeja Standar Tropical Lengan Panjang', 190000, null],
                ['Kemeja Tactical AM Lengan Panjang', 210000, null],
                ['Kemeja Tactical Tropical Lengan Panjang', 260000, null],
            ],
            'Tas & Totebag' => [
                ['Tas Serut', 6000, ['S' => 6000, 'M' => 10000, 'L' => 15000]],
                ['Totebag', 20000, ['S' => 20000, 'M' => 35000, 'L' => 55000]],
                ['Tas Ransel', 55000, ['S' => 55000, 'M' => 70000, 'L' => 85000]],
            ],
            'Tumbler' => [
                ['Tumbler Wedding', 25000, ['S' => 25000, 'M' => 40000, 'L' => 60000]],
                ['Tumbler Aluminium', 55000, ['S' => 55000, 'M' => 80000, 'L' => 100000, 'XL' => 150000]],
            ],
        ];
    }

    private function catalogue(Business $business, Outlet $outlet): void
    {
        $sku = 0;
        $sort = 0;

        foreach ($this->rows() as $categoryName => $products) {
            $category = ProductCategory::create([
                'business_id' => $business->id,
                'name'        => $categoryName,
                'slug'        => Str::slug($categoryName),
                'sort_order'  => ++$sort,
                'is_active'   => true,
            ]);

            foreach ($products as [$name, $price, $sizes]) {
                $product = Product::create([
                    'business_id'         => $business->id,
                    'outlet_id'           => $outlet->id,
                    'product_category_id' => $category->id,
                    'name'                => $name,
                    'sku'                 => 'CH' . str_pad((string) ++$sku, 3, '0', STR_PAD_LEFT),
                    'price'               => $price,
                    'cost_price'          => 0,
                    'is_active'           => true,
                    'is_stock_tracked'    => false,
                    'sort_order'          => $sku,
                ]);

                $sizes ??= array_fill_keys(self::APPAREL_SIZES, $price);

                $i = 0;
                foreach ($sizes as $size => $sizePrice) {
                    ProductVariant::create([
                        'product_id'       => $product->id,
                        'name'             => $size,
                        'price_adjustment' => $sizePrice - $price,
                        'is_active'        => true,
                        'sort_order'       => ++$i,
                    ]);
                }
            }
        }
    }
}
