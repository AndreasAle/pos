<?php

namespace App\Services\PlatformAgents\Sales;

/** Penawaran perangkat yang boleh disebut ke prospek. Tanpa itu: minta penawaran ke tim. */
final class HardwareCatalog
{
    /** Bundel yang benar-benar bisa ditawarkan: bundel approved, item approved + verified, harga lengkap, syarat terisi. */
    public function approvedBundle(): ?array
    {
        return $this->approvedBundles()[0] ?? $this->storeBundles()[0] ?? null;
    }

    /**
     * Paket bundling publik di yokapos.com/bundling (config/yoka_store.php). Keputusan pemilik 2026-11-24:
     * Adit boleh menyebut harga ini persis, tanpa diskon; stok dan jadwal pasang tetap dikonfirmasi tim.
     *
     * @return array<int,array>
     */
    public function storeBundles(): array
    {
        if (! config('yoka_store.adit_can_quote', true)) {
            return [];
        }
        $devices = (array) config('yoka_store.devices', []);

        return collect((array) config('yoka_store.bundles', []))->map(fn ($b, $key) => [
            'key' => $key, 'name' => $b['name'], 'initial_price' => (int) $b['price'],
            'includes_months' => (int) config('yoka_store.plan_months', 12),
            'renewal' => 'perpanjangan YokaBasic sesuai harga paket resmi',
            'item' => ['model' => collect($b['items'])->map(fn ($k) => $devices[$k]['name'] ?? $k)->implode(' + ')],
            'source' => 'store',
        ])->values()->all();
    }

    /** @return array<int,array> semua bundel yang boleh ditawarkan (mis. 1/3/6/12 bulan + printer). */
    public function approvedBundles(): array
    {
        $out = [];
        foreach ((array) config('yoka_hardware.bundles', []) as $key => $b) {
            $item = config('yoka_hardware.items.'.($b['item'] ?? ''));
            if (($b['status'] ?? null) === 'approved' && $item && ($item['status'] ?? null) === 'approved' && ($item['compatibility'] ?? null) === 'verified'
                && ! empty($b['initial_price']) && ! in_array(null, (array) ($b['terms'] ?? []), true)) {
                $out[] = ['key' => $key, 'item' => $item] + $b;
            }
        }

        return $out;
    }

    /** @return array<int,array> perangkat yang boleh disebut harganya. */
    public function approvedItems(): array
    {
        return array_values(array_filter((array) config('yoka_hardware.items', []), fn ($i) => ($i['status'] ?? null) === 'approved'
            && ($i['compatibility'] ?? null) === 'verified' && ! empty($i['selling_price'])));
    }

    /** Teks aman untuk prompt (tanpa harga beli). */
    public function promptFacts(): string
    {
        if ($store = $this->storeFacts()) {
            return $store;
        }
        $bundles = $this->approvedBundles();
        $items = $this->approvedItems();
        if (! $bundles && ! $items) {
            return 'Belum ada perangkat atau bundel resmi yang dijual Yoka. Jangan sebut harga printer, jangan janji printer gratis atau stok.';
        }
        $out = [];
        foreach ($items as $i) {
            $out[] = "{$i['model']} ({$i['type']}): Rp ".number_format((float) $i['selling_price'], 0, ',', '.');
        }
        foreach ($bundles as $bundle) {
            $out[] = "{$bundle['name']}: Rp ".number_format((float) $bundle['initial_price'], 0, ',', '.')." di awal (termasuk {$bundle['item']['model']} dan {$bundle['includes_months']} bulan paket), lalu {$bundle['renewal']}.";
        }

        return 'Penawaran perangkat RESMI (hanya ini yang boleh disebut): '.implode('; ', $out);
    }

    /** Harga resmi perangkat/bundel untuk penjaga harga balasan. */
    public function officialPrices(): array
    {
        $p = array_map(fn ($i) => (int) $i['selling_price'], $this->approvedItems());
        foreach ($this->approvedBundles() as $b) {
            $p[] = (int) $b['initial_price'];
        }


        return $p;
    }

    /** Fakta penawaran perangkat dari halaman bundling publik, untuk prompt. Kosong bila Adit tidak diizinkan menyebutnya. */
    private function storeFacts(): string
    {
        $bundles = $this->storeBundles();
        if (! $bundles) {
            return '';
        }
        $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
        $lines = array_map(fn ($b) => "{$b['name']} {$rp($b['initial_price'])} ({$b['item']['model']})", $bundles);
        $single = collect((array) config('yoka_store.devices', []))->map(fn ($d) => "{$d['name']} {$rp($d['price'])}")->implode('; ');

        return 'Penawaran perangkat RESMI (sama persis dengan yokapos.com/bundling; hanya ini yang boleh disebut). '
            .'Setiap paket bundling sudah termasuk aplikasi YokaBasic '.(int) config('yoka_store.plan_months', 12).' bulan, pemasangan (kunjungan di Palembang, Google Meet di luar kota), dan garansi tukar '.(int) config('yoka_store.warranty_days', 30).' hari. '
            .'Paket: '.implode('; ', $lines).'. Perangkat satuan: '.$single.'. '
            .'Aturan: tanpa diskon atau bonus; jangan menjanjikan stok, tanggal kirim, atau tanggal pasang (itu dikonfirmasi tim); pesanan diteruskan ke tim, bukan dibayar lewat checkout aplikasi; '
            .'yang sudah punya HP Android tidak wajib beli tablet. Rincian dan foto: yokapos.com/bundling. Harga langganan dan perbandingan paket: yokapos.com/harga.';
    }

    /**
     * Harga paket bundling dan perangkat satuan dari halaman publik. Hanya sah di kalimat yang menyebut
     * perangkat/paket (lihat CommercialPolicy::scrubReply), supaya angka seperti Rp50.000 tidak lolos sebagai "diskon".
     *
     * @return array<int,int>
     */
    public function storePrices(): array
    {
        if (! $this->storeBundles()) {
            return [];
        }

        return array_merge(array_map(fn ($b) => (int) $b['initial_price'], $this->storeBundles()),
            array_map(fn ($d) => (int) $d['price'], array_values((array) config('yoka_store.devices', []))));
    }
}
