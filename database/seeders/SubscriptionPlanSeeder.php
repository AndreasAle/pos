<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'        => 'Free Trial',
                'slug'        => 'free',
                'price'       => 0,
                'max_outlets' => 999,
                'max_users'   => 999,
                // Trial 7 hari membuka SEMUA fitur agar calon pelanggan bisa mencoba penuh sebelum upgrade
                'features'    => ['pos', 'inventory', 'reports', 'kitchen', 'loyalty', 'multi_outlet', 'export', 'api', 'priority_support'],
                'is_active'   => true,
                'sort_order'  => 1,
            ],
            [
                // YokaBasic (dulu Starter). Slug tetap 'starter' agar langganan lama tetap terhubung.
                // Daftar harga v2, 2026-11-24 (dari sheet harga pemilik): bayar di muka 3/6/12 bulan lebih hemat, harga bulanan tidak didiskon.
                'name'        => 'YokaBasic',
                'slug'        => 'starter',
                'price'       => 99000,
                'period_prices' => ['3' => 270000, '6' => 499000, '12' => 950000],
                'max_outlets' => 2,
                'max_users'   => 10,
                'features'    => ['pos', 'inventory', 'reports', 'kitchen', 'loyalty'],
                'is_active'   => true,
                'sort_order'  => 2,
            ],
            [
                'name'        => 'Business',
                'slug'        => 'business',
                'price'       => 199000,
                'period_prices' => ['3' => 549000, '6' => 999000, '12' => 1869000],
                'max_outlets' => 5,
                'max_users'   => 30,
                'features'    => ['pos', 'inventory', 'reports', 'kitchen', 'loyalty', 'multi_outlet', 'export'],
                'is_active'   => true,
                'sort_order'  => 3,
            ],
            [
                'name'        => 'Custom',
                'slug'        => 'enterprise',
                'price'       => 399000,
                'max_outlets' => 999,
                'max_users'   => 999,
                'features'    => ['pos', 'inventory', 'reports', 'kitchen', 'loyalty', 'multi_outlet', 'export', 'api', 'priority_support'],
                'is_active'   => true,
                'sort_order'  => 4,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
