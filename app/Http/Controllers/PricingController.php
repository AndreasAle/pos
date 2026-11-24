<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Services\AiCreditsReport;

/** Halaman publik: paket langganan (/harga) dan paket bundling perangkat (/bundling). */
class PricingController extends Controller
{
    public const WA = 'https://wa.me/6285891111795?text=';

    public function plans()
    {
        $plans = $this->planRows();

        return view('pricing.plans', [
            'plans' => $plans,
            'durations' => collect($plans)->flatMap(fn ($p) => array_keys($p['periods']))->unique()->sort()->values()->all(),
            'credits' => config('yoka_ai_credits.enabled') ? AiCreditsReport::facts() : null,
            'creditPacks' => (array) config('yoka_ai_credits.packs', []),
            'bundleFrom' => collect((array) config('yoka_store.bundles'))->min('price'),
            'offline' => (bool) config('pos.features.pos_offline'),
            'waLink' => self::WA.rawurlencode('Halo, saya mau tanya paket langganan Yoka POS'),
        ]);
    }

    public function bundles()
    {
        $devices = (array) config('yoka_store.devices');
        $basic12 = $this->basicYearly();

        $bundles = collect((array) config('yoka_store.bundles'))->map(function ($b, $slug) use ($devices, $basic12) {
            $separate = $basic12 + collect($b['items'])->sum(fn ($k) => $devices[$k]['price'] ?? 0);

            return $b + [
                'slug' => $slug,
                'devices' => collect($b['items'])->map(fn ($k) => ['key' => $k] + $devices[$k])->all(),
                'separate' => $separate,
                'saving' => max(0, $separate - $b['price']),
                'wa' => self::WA.rawurlencode("Halo, saya mau pesan {$b['name']} (Rp".number_format($b['price'], 0, ',', '.').') dari yokapos.com/bundling'),
                // Kelompok untuk filter di halaman: dihitung dari isi paket, bukan diketik manual.
                'groups' => array_values(array_filter([
                    count($b['items']) === 1 && ! in_array('mpos', $b['items'], true) ? 'hemat' : null,
                    in_array('tablet', $b['items'], true) ? 'tablet' : null,
                    in_array('mpos', $b['items'], true) ? 'mesin' : null,
                    count($b['items']) >= 3 ? 'lengkap' : null,
                ])),
            ];
        })->values()->all();

        return view('pricing.bundles', [
            'bundles' => $bundles,
            'devices' => $devices,
            'basic12' => $basic12,
            'months' => (int) config('yoka_store.plan_months', 12),
            'warrantyDays' => (int) config('yoka_store.warranty_days', 30),
            'waLink' => self::WA.rawurlencode('Halo, saya mau tanya paket bundling perangkat Yoka POS'),
        ]);
    }

    /** Paket berbayar dari tabel resmi (sumber yang sama dengan checkout). Fallback ke harga v2 bila DB belum siap. */
    private function planRows(): array
    {
        $meta = [
            'starter' => ['tag' => 'Untuk warung dan usaha kuliner yang baru mulai', 'features' => ['Kasir, QRIS, dan cetak struk', 'Produk, varian, add-on, dan promo', 'Stok bahan dan resep', 'Laporan penjualan dan estimasi profit', 'QR meja dan layar dapur', 'Poin pelanggan', '30 kredit Yoka AI tiap bulan']],
            'business' => ['tag' => 'Untuk kafe dan resto dengan beberapa cabang', 'features' => ['Semua fitur YokaBasic', 'Hingga 5 outlet dan 30 pengguna', 'Ekspor laporan ke Excel dan PDF', 'Laporan per outlet', '100 kredit Yoka AI tiap bulan'], 'featured' => true],
            'enterprise' => ['tag' => 'Untuk jaringan outlet atau kebutuhan khusus', 'features' => ['Outlet dan pengguna tanpa batas', 'Fitur dan integrasi sesuai kebutuhan', 'Pelatihan tim di lokasi', 'Dukungan prioritas'], 'custom' => true],
        ];
        try {
            $rows = SubscriptionPlan::where('is_active', true)->whereIn('slug', array_keys($meta))->orderBy('sort_order')->get();
        } catch (\Throwable) {
            $rows = collect();
        }
        if ($rows->isEmpty()) {
            $rows = collect([
                new SubscriptionPlan(['slug' => 'starter', 'name' => 'YokaBasic', 'price' => 99000, 'period_prices' => ['3' => 270000, '6' => 499000, '12' => 950000], 'max_outlets' => 2, 'max_users' => 10]),
                new SubscriptionPlan(['slug' => 'business', 'name' => 'Business', 'price' => 199000, 'period_prices' => ['3' => 549000, '6' => 999000, '12' => 1869000], 'max_outlets' => 5, 'max_users' => 30]),
                new SubscriptionPlan(['slug' => 'enterprise', 'name' => 'Custom', 'price' => 399000, 'max_outlets' => 999, 'max_users' => 999]),
            ]);
        }

        return $rows->map(fn ($p) => [
            'slug' => $p->slug, 'name' => $p->name, 'monthly' => (int) $p->price, 'periods' => $p->periods(),
            'outlets' => $p->max_outlets >= 999 ? 'Outlet tanpa batas' : $p->max_outlets.' outlet',
            'users' => $p->max_users >= 999 ? 'Pengguna tanpa batas' : $p->max_users.' pengguna',
        ] + $meta[$p->slug])->values()->all();
    }

    private function basicYearly(): int
    {
        try {
            return SubscriptionPlan::where('slug', 'starter')->first()?->priceFor((int) config('yoka_store.plan_months', 12)) ?? 950000;
        } catch (\Throwable) {
            return 950000;
        }
    }
}
