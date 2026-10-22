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
 * Live tenant for POS DIKI STORE — shirts, bags, tumblers and drinks sold at
 * the Kumbang exhibition.
 *
 * Retail, not F&B. Clothing sizes are product variants at the listed price;
 * everything else is a single item.
 *
 * Never wipes anything; if the shop already exists it stops.
 *
 *   php artisan db:seed --class=DikiStoreSeeder --force
 */
class DikiStoreSeeder extends Seeder
{
    private const BUSINESS_SLUG = 'diki-store';

    private const ADULT_SIZES = ['S', 'M', 'L', 'XL', 'XXL'];

    private const KID_SIZES = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

    public function run(): void
    {
        if (Business::where('slug', self::BUSINESS_SLUG)->exists()) {
            $this->command?->warn('POS DIKI STORE sudah ada — seeder dilewati, tidak ada data yang diubah.');

            return;
        }

        // Fail before creating anything if a credential is missing.
        foreach (['DK_PASSWORD', 'DK_PIN'] as $key) {
            if ((string) env($key, '') === '') {
                throw new \RuntimeException("{$key} belum diisi di .env — seeder dibatalkan.");
            }
        }

        $business = $this->business();
        $outlet   = $this->outlet($business);

        $this->users($business, $outlet);
        $this->catalogue($business, $outlet);

        $this->command?->info('');
        $this->command?->info('  POS DIKI STORE siap.');
        $this->command?->info('  Login : dikistore@conweb.id');
        $this->command?->info('  Tablet kasir — buka /kasir, kode outlet DK01');
        $this->command?->info('');
    }

    private function business(): Business
    {
        $business = Business::create([
            'name'     => 'POS DIKI STORE',
            'slug'     => self::BUSINESS_SLUG,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'settings' => [
                'business_type'        => 'retail',
                'receipt_header'       => 'DIKI STORE',
                'receipt_footer'       => 'Terima kasih sudah belanja di Diki Store!',
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
            'name'        => 'Diki Store',
            'code'        => 'DK01',
            'is_active'   => true,
        ]);
    }

    /**
     * One account that runs the register and can also fix prices, so it is
     * an admin. Password and PIN come from DK_PASSWORD / DK_PIN in the
     * server's .env so they never land in git.
     */
    private function users(Business $business, Outlet $outlet): void
    {
        User::create([
            'business_id'       => $business->id,
            'outlet_id'         => $outlet->id,
            'name'              => 'Kasir Diki Store',
            'email'             => 'dikistore@conweb.id',
            'email_verified_at' => now(),
            'password'          => Hash::make((string) env('DK_PASSWORD')),
            'pin'               => Hash::make((string) env('DK_PIN')),
            'role'              => 'admin',
            'is_active'         => true,
        ]);
    }

    /**
     * category => [product name, price, sizes]
     * sizes: null for a single item, or a list of sizes at the same price.
     */
    private function rows(): array
    {
        $adult = self::ADULT_SIZES;

        return [
            'Kaos' => [
                ['Kaos 30s Dewasa', 75000, $adult],
                ['Kaos 24s Dewasa', 85000, $adult],
                ['Kaos 20s Dewasa', 100000, $adult],
                ['Kaos 16s', 150000, $adult],
                ['Kaos Boxy Dewasa', 85000, $adult],
                ['Kaos Jakarta Dewasa', 70000, $adult],
                ['Kaos Anak-anak', 60000, self::KID_SIZES],
                ['Kaos Kerah V', 35000, $adult],
                ['Kaos Kenteng', 120000, $adult],
            ],
            'Kaos Berkerah' => [
                ['Kaos PE Berkerah', 75000, $adult],
                ['Lakos Biasa', 110000, $adult],
                ['Lakos Super', 200000, $adult],
            ],
            'Jaket & Topi' => [
                ['Jaket Sweater', 250000, $adult],
                ['Topi', 60000, null],
            ],
            'Tas' => [
                ['Tas Serut Warna-warni', 15000, null],
                ['Tas Serut Putih', 25000, null],
                ['Hand Bag Bagus (Lis Hijau / Tanpa Lis)', 125000, null],
                ['Hand Bag PMI', 35000, null],
                ['Hand Bag Hitam Bagus', 35000, null],
            ],
            'Tumbler' => [
                ['Tumbler Stainless', 100000, null],
                ['Tumbler Kaca', 50000, null],
            ],
            'Aksesoris' => [
                ['Gantungan Kunci Akrilik 1 Muka', 10000, null],
                ['Buku Memo', 10000, null],
                ['Monte-monte (1 gram)', 2000, null],
            ],
            'Minuman & Snack' => [
                ['Minuman Es / Hot', 20000, null],
                ['Popcorn', 10000, null],
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
                    'sku'                 => 'DK' . str_pad((string) ++$sku, 3, '0', STR_PAD_LEFT),
                    'price'               => $price,
                    'cost_price'          => 0,
                    'is_active'           => true,
                    'is_stock_tracked'    => false,
                    'sort_order'          => $sku,
                ]);

                foreach ($sizes ?? [] as $i => $size) {
                    ProductVariant::create([
                        'product_id'       => $product->id,
                        'name'             => $size,
                        'price_adjustment' => 0,
                        'is_active'        => true,
                        'sort_order'       => $i + 1,
                    ]);
                }
            }
        }
    }
}
