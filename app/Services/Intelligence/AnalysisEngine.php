<?php

namespace App\Services\Intelligence;

use Illuminate\Support\Arr;

class AnalysisEngine
{
    public function analyze(string $tool, array $result): array
    {
        $data = $result['data'] ?? [];
        $analysis = match ($tool) {
            'sales_summary' => $this->salesSummary($data),
            'sales_series' => $this->salesSeries($data),
            'payment_breakdown' => $this->payments($data),
            'product_performance' => $this->products($data),
            'stock_status' => $this->stock($data),
            'peak_hours' => $this->peakHours($data),
            'outlet_performance' => $this->outlets($data),
            default => ['findings' => [], 'actions' => []],
        };

        return [
            'findings' => array_values(array_filter($analysis['findings'] ?? [])),
            'actions' => array_values(array_filter($analysis['actions'] ?? [])),
            'caveats' => array_values(array_unique(array_filter(array_merge(
                $analysis['caveats'] ?? [],
                $result['warnings'] ?? [],
            )))),
        ];
    }

    public function localNarrative(string $tool, array $result, array $analysis): ?string
    {
        if (empty($analysis['findings'])) {
            return null;
        }

        $text = implode(' ', array_slice($analysis['findings'], 0, 3));
        if ($action = Arr::first($analysis['actions'])) {
            $text .= ' Langkah yang paling masuk akal: '.$action;
        }

        return $text;
    }

    private function salesSummary(array $data): array
    {
        $sales = (float) ($data['net_sales'] ?? 0);
        $transactions = (int) ($data['transactions'] ?? 0);
        if ($transactions === 0) {
            return ['findings' => ['Belum ada transaksi paid pada periode ini, jadi performa penjualan belum dapat dinilai.'], 'actions' => []];
        }

        $discounts = (float) ($data['discounts'] ?? 0);
        $profit = (float) ($data['estimated_gross_profit'] ?? 0);
        $coverage = $data['cogs_coverage_percent'] ?? null;
        $findings = [
            sprintf('Penjualan bersih tercatat %s dari %d transaksi, dengan rata-rata %s per transaksi.', $this->money($sales), $transactions, $this->money($data['average_order'] ?? 0)),
            $discounts > 0 ? sprintf('Diskon menyerap %.1f%% dari nilai item sebelum diskon.', $this->percent($discounts, $sales + $discounts)) : 'Tidak ada diskon tercatat pada transaksi di periode ini.',
        ];
        if ($coverage === 100 && $sales > 0) {
            $findings[] = sprintf('Estimasi margin kotor berada di %.1f%%; ini belum memperhitungkan biaya operasional.', $this->percent($profit, $sales));
        }

        return ['findings' => $findings, 'actions' => ['Gunakan rincian produk atau jam ramai untuk menemukan pendorong utama angka tersebut.']];
    }

    private function salesSeries(array $data): array
    {
        $points = collect($data['points'] ?? [])->values();
        if ($points->isEmpty()) {
            return ['findings' => ['Belum ada transaksi paid yang dapat dianalisis sebagai tren.'], 'actions' => []];
        }

        $peak = $points->sortByDesc('value')->first();
        $total = (float) $points->sum('value');
        $findings = [
            sprintf('Ada %d hari aktif dengan total penjualan %s; rata-rata per hari aktif %s.', $points->count(), $this->money($total), $this->money($total / $points->count())),
            sprintf('Puncak terjadi pada %s sebesar %s dari %d transaksi.', $peak['date'], $this->money($peak['value']), $peak['transactions']),
        ];
        if ($points->count() >= 2) {
            $first = $points->first();
            $last = $points->last();
            $change = (float) $first['value'] > 0 ? (((float) $last['value'] - (float) $first['value']) / (float) $first['value']) * 100 : null;
            if ($change !== null) {
                $findings[] = sprintf('Dari hari aktif pertama ke terakhir, penjualan %s %.1f%% (%s menjadi %s).', $change >= 0 ? 'naik' : 'turun', abs($change), $this->money($first['value']), $this->money($last['value']));
            }
        }

        return [
            'findings' => $findings,
            'actions' => ['Periksa produk dan jam transaksi pada hari puncak sebelum menambah stok atau tenaga kerja.'],
            'caveats' => ['Hari tanpa transaksi tidak dihitung sebagai titik nol maupun hari aktif.'],
        ];
    }

    private function payments(array $data): array
    {
        $rows = collect($data['payments'] ?? []);
        $total = (float) $rows->sum('amount');
        if ($rows->isEmpty() || $total <= 0) {
            return ['findings' => ['Belum ada pembayaran tercatat pada periode ini.'], 'actions' => []];
        }
        $top = $rows->sortByDesc('amount')->first();

        return [
            'findings' => [sprintf('%s mendominasi %.1f%% dari nilai pembayaran tercatat (%s).', strtoupper($top['payment_method']), $this->percent($top['amount'], $total), $this->money($top['amount'])), sprintf('Terdapat %d metode pembayaran yang digunakan.', $rows->count())],
            'actions' => ['Pastikan metode dominan tetap siap, tetapi jangan menonaktifkan opsi lain hanya berdasarkan periode ini.'],
        ];
    }

    private function products(array $data): array
    {
        $rows = collect($data['products'] ?? []);
        if ($rows->isEmpty()) {
            return ['findings' => ['Belum ada produk terjual pada periode ini.'], 'actions' => []];
        }
        $topQty = $rows->sortByDesc('quantity')->first();
        $topSales = $rows->sortByDesc('net_sales')->first();

        return [
            'findings' => [sprintf('%s memimpin volume dengan %s unit terjual.', $topQty['name'], $topQty['quantity']), sprintf('%s menghasilkan penjualan tertinggi di daftar ini sebesar %s.', $topSales['name'], $this->money($topSales['net_sales']))],
            'actions' => [$topQty['name'] === $topSales['name'] ? 'Jaga ketersediaan produk unggulan ini dan bahan resepnya.' : 'Bandingkan harga dan margin kedua produk sebelum menentukan produk yang akan dipromosikan.'],
        ];
    }

    private function stock(array $data): array
    {
        $rows = collect($data['ingredients'] ?? []);
        $low = $rows->where('low', true);

        return [
            'findings' => [$low->isEmpty() ? 'Tidak ada bahan di daftar ini yang berada pada atau di bawah batas minimum.' : sprintf('%d dari %d bahan yang diperiksa berada pada atau di bawah batas minimum.', $low->count(), $rows->count())],
            'actions' => $low->isEmpty() ? [] : ['Prioritaskan pemeriksaan fisik bahan dengan estimasi hari tersisa paling pendek sebelum membuat pembelian.'],
        ];
    }

    private function peakHours(array $data): array
    {
        $rows = collect($data['hours'] ?? []);
        if ($rows->isEmpty()) {
            return ['findings' => ['Belum cukup transaksi untuk menemukan jam ramai.'], 'actions' => []];
        }
        $top = $rows->sortByDesc('transactions')->first();

        return ['findings' => [sprintf('Pukul %02d.00 mencatat transaksi terbanyak, yaitu %d transaksi dengan penjualan %s.', $top['hour'], $top['transactions'], $this->money($top['net_sales']))], 'actions' => ['Cocokkan pola ini di beberapa hari sebelum mengubah jadwal tenaga kerja.']];
    }

    private function outlets(array $data): array
    {
        $rows = collect($data['outlets'] ?? []);
        if ($rows->isEmpty()) {
            return ['findings' => ['Belum ada penjualan outlet untuk dibandingkan.'], 'actions' => []];
        }
        $top = $rows->sortByDesc('net_sales')->first();

        return ['findings' => [sprintf('%s mencatat penjualan tertinggi sebesar %s dari %d transaksi.', $top['name'], $this->money($top['net_sales']), $top['transactions'])], 'actions' => ['Bandingkan ukuran outlet, jam buka, dan jumlah transaksi sebelum menyimpulkan outlet lain berkinerja buruk.']];
    }

    private function percent(float|int $part, float|int $whole): float
    {
        return $whole > 0 ? ($part / $whole) * 100 : 0;
    }

    private function money(float|int $value): string
    {
        return 'Rp'.number_format((float) $value, 0, ',', '.');
    }
}
