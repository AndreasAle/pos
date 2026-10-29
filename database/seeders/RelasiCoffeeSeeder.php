<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductCategory;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Live tenant for Relasi Coffee, Palembang — coffee, spiced drinks and food.
 *
 * Prices are the ones on the printed menu. cost_price is the HPP from the
 * owner's HPP sheets, with the sums that were added up wrong recalculated.
 * Toppings are add-ons on every drink.
 *
 * Never wipes anything; if the shop already exists it stops.
 *
 *   php artisan db:seed --class=RelasiCoffeeSeeder --force
 */
class RelasiCoffeeSeeder extends Seeder
{
    private const BUSINESS_SLUG = 'relasi-coffee';

    private const TOPPINGS = [
        ['Ice cream', 3000],
        ['Coffee jelly', 2500],
    ];

    public function run(): void
    {
        if (Business::where('slug', self::BUSINESS_SLUG)->exists()) {
            $this->command?->warn('Relasi Coffee sudah ada — seeder dilewati, tidak ada data yang diubah.');

            return;
        }

        // Fail before creating anything if a credential is missing.
        foreach (['RC_PASSWORD', 'RC_PIN'] as $key) {
            if ((string) env($key, '') === '') {
                throw new \RuntimeException("{$key} belum diisi di .env — seeder dibatalkan.");
            }
        }

        $business = $this->business();
        $outlet   = $this->outlet($business);

        $this->users($business, $outlet);
        $this->catalogue($business, $outlet);

        $this->command?->info('');
        $this->command?->info('  Relasi Coffee siap.');
        $this->command?->info('  Login : relasicoffee@conweb.id');
        $this->command?->info('  Tablet kasir — buka /kasir, kode outlet RC01');
        $this->command?->info('');
    }

    private function business(): Business
    {
        $business = Business::create([
            'name'     => 'Relasi Coffee',
            'slug'     => self::BUSINESS_SLUG,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'settings' => [
                'receipt_header'       => 'RELASI COFFEE',
                'receipt_footer'       => 'Good Food, Good Coffee, Better Connection',
                'receipt_size'         => '58mm',
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
            'name'        => 'Relasi Coffee Palembang',
            'code'        => 'RC01',
            'address'     => 'Jl. Jenderal Sudirman Km 3,5 No 19, Pahlawan, Kemuning, Palembang',
            'is_active'   => true,
        ]);
    }

    /**
     * One admin account. Password and PIN come from RC_PASSWORD / RC_PIN in
     * the server's .env so they never land in git.
     */
    private function users(Business $business, Outlet $outlet): void
    {
        User::create([
            'business_id'       => $business->id,
            'outlet_id'         => $outlet->id,
            'name'              => 'Admin Relasi Coffee',
            'email'             => 'relasicoffee@conweb.id',
            'email_verified_at' => now(),
            'password'          => Hash::make((string) env('RC_PASSWORD')),
            'pin'               => Hash::make((string) env('RC_PIN')),
            'role'              => 'admin',
            'is_active'         => true,
        ]);
    }

    /**
     * category => [is_drink, [[product name, price, HPP], ...]]
     */
    private function rows(): array
    {
        return [
            'Traditional Coffee' => [true, [
                // No HPP sheet for tubruk: 20 g coffee + cup, as in Kopi Susu.
                ['Kopi Tubruk', 15000, 5000],
                ['Kopi Susu', 17000, 5600],
            ]],
            'Spiced Coffee' => [true, [
                ['Kopi Jahe', 18000, 10100],
                ['Bajigur', 12000, 3650],
                ['Bajigur Jahe', 16000, 8950],
            ]],
            'Herb Drink' => [true, [
                ['Jahe Serai Jeruk Nipis', 18000, 9800],
                ['Rempah Madu', 27000, 17200],
            ]],
            'Relasi Italiano' => [true, [
                ['Relasi Segar Likisa (Lichee-Markisa)', 28000, 19600],
                ['Melon Fruit Punch', 17000, 8050],
            ]],
            'Relasi Usus Sehat' => [true, [
                ['Yoghurt Original', 20000, 11300],
                ['Yoghurt Lichee', 25000, 11950],
                ['Yoghurt Mango', 25000, 11950],
                ['Yoghurt Strawberry', 25000, 11950],
            ]],
            'Relasi Creamy' => [true, [
                ['Matcha Relasi', 27000, 11220],
                ['Chocolate Relasi', 27000, 8900],
            ]],
            'Porta Filter Coffee' => [true, [
                ['Espresso (Single)', 20000, 3100],
                // No HPP sheet for a double: two shots of the single.
                ['Espresso (Double)', 40000, 6100],
                ['Americano', 23000, 6500],
                ['Cappuccino', 28000, 8300],
                ['Caffe Latte', 28000, 8300],
                ['Mochacino', 28000, 11400],
                ['Milky Coffee Jelly', 28000, 7890],
            ]],
            'Rekomendasi Relasi Drink' => [true, [
                ['Kopi Jantan', 25000, 13900],
                ['STMJ (Susu-Telur-Jahe-Madu)', 27000, 17100],
                ['Pinang Muda', 28000, 11850],
            ]],
            'Flavored Tea' => [true, [
                ['Strawberry Tea', 25000, 8160],
                ['Passion Tea', 25000, 8310],
                ['Lichee Tea', 25000, 8160],
            ]],
            'Mie Goreng / Kuah' => [false, [
                ['Mie Goreng Ayam', 28000, 12281],
                ['Mie Goreng Seafood', 31000, 12956],
                ['Mie Goreng R', 35000, 14331],
            ]],
            'Indomie Selera Ku' => [false, [
                ['Indomie Goreng', 23000, 9400],
                ['Indomie Kuah', 23000, 9400],
            ]],
            'Nasi Goreng' => [false, [
                ['Nasi Goreng Special', 38000, 15712],
                ['Nasi Goreng Ayam', 31000, 12512],
                ['Nasi Goreng Seafood', 34000, 13587],
                ['Nasi Goreng R', 26000, 10267],
                ['Nasi Goreng Hongkong', 23000, 7837],
                ['Nasi Goreng Kampung', 26000, 11142],
            ]],
            'Toast' => [false, [
                ['Roti Bakar Keju', 21000, 8350],
                ['Roti Bakar Chocolate', 26000, 10677],
                ['Kaya Toast', 20000, 8050],
                ['Peanut Butter Toast', 22000, 8750],
            ]],
            'Snack' => [false, [
                ['French Fries', 27000, 11000],
                ['French Fries Balado', 28000, 11000],
                ['Mix Platter', 33000, 13350],
                ['Onion Ring', 28000, 9500],
                ['Udang Goreng Tepung', 25000, 10100],
            ]],
            'Rice Bowl' => [false, [
                ['Rice Bowl Sambal Matah', 28000, 12312],
                ['Rice Bowl Ayam Lada Hitam', 30000, 13737],
                ['Rice Bowl Chicken Katsu', 32000, 14687],
            ]],
            'Ayam Fillet' => [false, [
                ['Ayam Lada Hitam', 23000, 8550],
                ['Ayam Lada Garam', 20000, 7250],
                ['Ayam Sambal Matah', 32000, 13250],
                ['Ayam Asam Manis', 25000, 9970],
            ]],
            'Capcay' => [false, [
                ['Capcay', 20000, 7875],
                ['Capcay Seafood', 21000, 7500],
            ]],
        ];
    }

    private function catalogue(Business $business, Outlet $outlet): void
    {
        $sku = 0;
        $sort = 0;

        foreach ($this->rows() as $categoryName => [$isDrink, $products]) {
            $category = ProductCategory::create([
                'business_id' => $business->id,
                'name'        => $categoryName,
                'slug'        => Str::slug($categoryName),
                'sort_order'  => ++$sort,
                'is_active'   => true,
            ]);

            foreach ($products as [$name, $price, $cost]) {
                $product = Product::create([
                    'business_id'         => $business->id,
                    'outlet_id'           => $outlet->id,
                    'product_category_id' => $category->id,
                    'name'                => $name,
                    'sku'                 => 'RC' . str_pad((string) ++$sku, 3, '0', STR_PAD_LEFT),
                    'price'               => $price,
                    'cost_price'          => $cost,
                    'is_active'           => true,
                    'is_stock_tracked'    => false,
                    'sort_order'          => $sku,
                ]);

                if (! $isDrink) {
                    continue;
                }

                foreach (self::TOPPINGS as $i => [$topping, $toppingPrice]) {
                    ProductAddon::create([
                        'product_id' => $product->id,
                        'name'       => $topping,
                        'price'      => $toppingPrice,
                        'is_active'  => true,
                        'sort_order' => $i + 1,
                    ]);
                }
            }
        }
    }
}
