<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoBusinessSeeder extends Seeder
{
    public function run(): void
    {
        // ── Business ─────────────────────────────────────────────────────────
        $business = Business::create([
            'name'     => 'Demo Cafe Nusantara',
            'slug'     => 'demo-cafe-nusantara',
            'phone'    => '0811-1234-5678',
            'email'    => 'owner@demo.com',
            'address'  => 'Jl. Contoh No. 1, Kota Demo',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'is_active'=> true,
            'settings' => [
                'enable_tax'           => true,
                'tax_percent'          => 10,
                'enable_service'       => false,
                'service_percent'      => 5,
                'allow_negative_stock' => false,
                'receipt_header'       => 'Demo Cafe Nusantara',
                'receipt_footer'       => 'Terima kasih, sampai jumpa lagi!',
                'receipt_size'         => '80mm',
                'points_per_rupiah'    => 1,
            ],
        ]);

        // ── Outlets ───────────────────────────────────────────────────────────
        $outlet1 = Outlet::create([
            'business_id' => $business->id,
            'name'        => 'Outlet Utama',
            'code'        => 'MAIN',
            'phone'       => '0811-0000-0001',
            'address'     => 'Jl. Contoh No. 1',
            'is_active'   => true,
        ]);
        $outlet2 = Outlet::create([
            'business_id' => $business->id,
            'name'        => 'Outlet Cabang',
            'code'        => 'CB01',
            'phone'       => '0811-0000-0002',
            'address'     => 'Jl. Contoh No. 2',
            'is_active'   => true,
        ]);

        // ── Users ─────────────────────────────────────────────────────────────
        $owner = User::create([
            'business_id' => $business->id,
            'outlet_id'   => $outlet1->id,
            'name'        => 'Budi Owner',
            'email'       => 'owner@demo.com',
            'password'    => Hash::make('password'),
            'role'        => 'owner',
            'is_active'   => true,
        ]);
        $admin = User::create([
            'business_id' => $business->id,
            'outlet_id'   => $outlet1->id,
            'name'        => 'Siti Admin',
            'email'       => 'admin@demo.com',
            'password'    => Hash::make('password'),
            'role'        => 'admin',
            'is_active'   => true,
        ]);
        $cashier = User::create([
            'business_id' => $business->id,
            'outlet_id'   => $outlet1->id,
            'name'        => 'Andi Kasir',
            'email'       => 'cashier@demo.com',
            'password'    => Hash::make('password'),
            'role'        => 'cashier',
            'is_active'   => true,
        ]);
        $kitchen = User::create([
            'business_id' => $business->id,
            'outlet_id'   => $outlet1->id,
            'name'        => 'Roni Kitchen',
            'email'       => 'kitchen@demo.com',
            'password'    => Hash::make('password'),
            'role'        => 'kitchen',
            'is_active'   => true,
        ]);

        // ── Subscription ──────────────────────────────────────────────────────
        $freePlan = SubscriptionPlan::where('slug', 'free')->first();
        BusinessSubscription::create([
            'business_id'          => $business->id,
            'subscription_plan_id' => $freePlan->id,
            'starts_at'            => today(),
            'ends_at'              => today()->addDays(30),
            'status'               => 'trial',
        ]);

        // ── Categories ────────────────────────────────────────────────────────
        $catMinuman   = ProductCategory::create(['business_id'=>$business->id,'name'=>'Minuman','color'=>'#3b82f6','sort_order'=>1,'is_active'=>true,'slug'=>'minuman']);
        $catMakanan   = ProductCategory::create(['business_id'=>$business->id,'name'=>'Makanan','color'=>'#f59e0b','sort_order'=>2,'is_active'=>true,'slug'=>'makanan']);
        $catSnack     = ProductCategory::create(['business_id'=>$business->id,'name'=>'Snack','color'=>'#10b981','sort_order'=>3,'is_active'=>true,'slug'=>'snack']);
        $catKopi      = ProductCategory::create(['business_id'=>$business->id,'name'=>'Kopi','color'=>'#6b3a2a','sort_order'=>4,'is_active'=>true,'slug'=>'kopi']);

        // ── Products ──────────────────────────────────────────────────────────
        $products = [
            ['name'=>'Es Kopi Susu',       'cat'=>$catKopi->id,    'price'=>25000,'cost'=>10000],
            ['name'=>'Americano',           'cat'=>$catKopi->id,    'price'=>20000,'cost'=>7000],
            ['name'=>'Cappuccino',          'cat'=>$catKopi->id,    'price'=>28000,'cost'=>12000],
            ['name'=>'Es Teh Manis',        'cat'=>$catMinuman->id, 'price'=>8000, 'cost'=>2000],
            ['name'=>'Jus Alpukat',         'cat'=>$catMinuman->id, 'price'=>18000,'cost'=>7000],
            ['name'=>'Es Jeruk',            'cat'=>$catMinuman->id, 'price'=>10000,'cost'=>3000],
            ['name'=>'Nasi Ayam Geprek',    'cat'=>$catMakanan->id, 'price'=>25000,'cost'=>12000],
            ['name'=>'Mie Goreng',          'cat'=>$catMakanan->id, 'price'=>20000,'cost'=>8000],
            ['name'=>'Roti Bakar',          'cat'=>$catSnack->id,   'price'=>15000,'cost'=>5000],
            ['name'=>'Pisang Goreng Crispy','cat'=>$catSnack->id,   'price'=>12000,'cost'=>4000],
        ];

        foreach ($products as $i => $p) {
            Product::create([
                'business_id'         => $business->id,
                'product_category_id' => $p['cat'],
                'name'                => $p['name'],
                'price'               => $p['price'],
                'cost_price'          => $p['cost'],
                'is_active'           => true,
                'is_stock_tracked'    => $i < 3,
                'sort_order'          => $i + 1,
            ]);
        }

        // ── Ingredients ───────────────────────────────────────────────────────
        $ingredients = [
            ['name'=>'Kopi Robusta',    'unit'=>'gram', 'stock'=>500, 'min'=>100, 'cost'=>80],
            ['name'=>'Susu UHT',        'unit'=>'ml',   'stock'=>2000,'min'=>500, 'cost'=>15],
            ['name'=>'Gula Pasir',      'unit'=>'gram', 'stock'=>1000,'min'=>200, 'cost'=>14],
            ['name'=>'Syrup Cokelat',   'unit'=>'ml',   'stock'=>500, 'min'=>100, 'cost'=>25],
            ['name'=>'Es Batu',         'unit'=>'gram', 'stock'=>2000,'min'=>500, 'cost'=>2],
        ];

        $ingList = [];
        foreach ($ingredients as $ing) {
            $ingList[] = Ingredient::create([
                'business_id'   => $business->id,
                'name'          => $ing['name'],
                'unit'          => $ing['unit'],
                'current_stock' => $ing['stock'],
                'minimum_stock' => $ing['min'],
                'average_cost'  => $ing['cost'],
                'is_active'     => true,
            ]);
        }

        // ── Recipe for Es Kopi Susu ───────────────────────────────────────────
        $esKopiSusu = Product::where('business_id', $business->id)->where('name', 'Es Kopi Susu')->first();
        if ($esKopiSusu) {
            $recipe = Recipe::create(['product_id'=>$esKopiSusu->id,'business_id'=>$business->id]);
            RecipeItem::create(['recipe_id'=>$recipe->id,'ingredient_id'=>$ingList[0]->id,'qty'=>18]);   // kopi
            RecipeItem::create(['recipe_id'=>$recipe->id,'ingredient_id'=>$ingList[1]->id,'qty'=>150]);  // susu
            RecipeItem::create(['recipe_id'=>$recipe->id,'ingredient_id'=>$ingList[2]->id,'qty'=>15]);   // gula
            RecipeItem::create(['recipe_id'=>$recipe->id,'ingredient_id'=>$ingList[4]->id,'qty'=>200]);  // es
        }

        // ── Customers ─────────────────────────────────────────────────────────
        $customers = [
            ['name'=>'Dewi Pelanggan',  'phone'=>'08111111111'],
            ['name'=>'Ahmad Reguler',   'phone'=>'08222222222'],
            ['name'=>'Sari Member',     'phone'=>'08333333333'],
        ];
        foreach ($customers as $c) {
            Customer::create(['business_id'=>$business->id,'name'=>$c['name'],'phone'=>$c['phone'],'is_active'=>true]);
        }

        // ── Demo Orders ───────────────────────────────────────────────────────
        $shift = CashierShift::create([
            'business_id'  => $business->id,
            'outlet_id'    => $outlet1->id,
            'user_id'      => $cashier->id,
            'opening_cash' => 200000,
            'opened_at'    => now()->subHours(4),
            'status'       => 'open',
        ]);

        $paidProducts = Product::where('business_id', $business->id)->take(3)->get();
        for ($i = 1; $i <= 5; $i++) {
            $prod  = $paidProducts->random();
            $qty   = rand(1, 3);
            $sub   = $prod->price * $qty;
            $tax   = round($sub * 0.1);
            $total = $sub + $tax;

            $order = Order::create([
                'business_id'    => $business->id,
                'outlet_id'      => $outlet1->id,
                'user_id'        => $cashier->id,
                'cashier_shift_id'=> $shift->id,
                'order_number'   => 'ORD-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'subtotal'       => $sub,
                'tax_amount'     => $tax,
                'grand_total'    => $total,
                'paid_amount'    => $total,
                'change_amount'  => 0,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'status'         => 'paid',
                'kitchen_status' => 'completed',
                'ordered_at'     => now()->subMinutes(rand(10, 240)),
            ]);

            OrderItem::create([
                'order_id'     => $order->id,
                'product_id'   => $prod->id,
                'product_name' => $prod->name,
                'price'        => $prod->price,
                'cost_price'   => $prod->cost_price,
                'qty'          => $qty,
                'subtotal'     => $sub,
                'kitchen_status'=> 'completed',
            ]);
        }
    }
}
