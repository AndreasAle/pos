<?php

namespace App\Services\PlatformAgents\Sales;

/** Penawaran perangkat yang boleh disebut ke prospek. Tanpa itu: minta penawaran ke tim. */
final class HardwareCatalog
{
    /** Bundel yang benar-benar bisa ditawarkan: bundel approved, item approved + verified, harga lengkap, syarat terisi. */
    public function approvedBundle(): ?array
    {
        foreach ((array) config('yoka_hardware.bundles', []) as $key => $b) {
            $item = config('yoka_hardware.items.'.($b['item'] ?? ''));
            if (($b['status'] ?? null) === 'approved' && $item && ($item['status'] ?? null) === 'approved' && ($item['compatibility'] ?? null) === 'verified'
                && ! empty($b['initial_price']) && ! in_array(null, (array) ($b['terms'] ?? []), true)) {
                return ['key' => $key, 'item' => $item] + $b;
            }
        }

        return null;
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
        $bundle = $this->approvedBundle();
        $items = $this->approvedItems();
        if (! $bundle && ! $items) {
            return 'Belum ada perangkat atau bundel resmi yang dijual Yoka. Jangan sebut harga printer, jangan janji printer gratis atau stok.';
        }
        $out = [];
        foreach ($items as $i) {
            $out[] = "{$i['model']} ({$i['type']}): Rp ".number_format((float) $i['selling_price'], 0, ',', '.');
        }
        if ($bundle) {
            $out[] = "{$bundle['name']}: Rp ".number_format((float) $bundle['initial_price'], 0, ',', '.')." di awal (termasuk {$bundle['item']['model']} dan {$bundle['includes_months']} bulan paket), lalu {$bundle['renewal']}.";
        }

        return 'Penawaran perangkat RESMI (hanya ini yang boleh disebut): '.implode('; ', $out);
    }

    /** Harga resmi perangkat/bundel untuk penjaga harga balasan. */
    public function officialPrices(): array
    {
        $p = array_map(fn ($i) => (int) $i['selling_price'], $this->approvedItems());
        if ($b = $this->approvedBundle()) {
            $p[] = (int) $b['initial_price'];
        }

        return $p;
    }
}
