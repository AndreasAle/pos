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
                'name'        => 'Starter',
                'slug'        => 'starter',
                'price'       => 99000,
                'max_outlets' => 2,
                'max_users'   => 10,
                'features'    => ['pos', 'inventory', 'reports', 'kitchen', 'loyalty'],
                'is_active'   => true,
                'sort_order'  => 2,
            ],
            [
                'name'        => 'Business',
                'slug'        => 'business',
                'price'       => 249000,
                'max_outlets' => 5,
                'max_users'   => 30,
                'features'    => ['pos', 'inventory', 'reports', 'kitchen', 'loyalty', 'multi_outlet', 'export'],
                'is_active'   => true,
                'sort_order'  => 3,
            ],
            [
                'name'        => 'Enterprise',
                'slug'        => 'enterprise',
                'price'       => 499000,
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
