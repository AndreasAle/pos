<?php

namespace App\Services\Intelligence;

class MetricDictionary
{
    public const VERSION = '1.1';

    public function definitions(array $keys = []): array
    {
        $all = [
            'gross_item_sales' => ['label' => 'Penjualan kotor item', 'definition' => 'Jumlah subtotal item pada order berstatus paid sebelum diskon order.', 'unit' => 'IDR'],
            'discounts' => ['label' => 'Diskon', 'definition' => 'Jumlah discount_amount pada order berstatus paid.', 'unit' => 'IDR'],
            'net_sales' => ['label' => 'Penjualan bersih', 'definition' => 'Subtotal order paid dikurangi diskon. Pajak, service, tip, delivery, dan refund dipisahkan.', 'unit' => 'IDR'],
            'payment_collected' => ['label' => 'Pembayaran tercatat', 'definition' => 'Pembayaran order paid yang tercatat di POS per metode. Order non-split = grand_total pada metode utamanya; order split = baris order_payments, dengan kembalian dikurangkan dari baris tunai. Bukan settlement atau biaya gateway.', 'unit' => 'IDR'],
            'transactions' => ['label' => 'Transaksi', 'definition' => 'Jumlah order unik berstatus paid dalam interval.', 'unit' => 'order'],
            'aov' => ['label' => 'Rata-rata transaksi', 'definition' => 'Penjualan bersih dibagi transaksi paid; N/A bila tidak ada transaksi.', 'unit' => 'IDR/order'],
            'gross_profit_estimate' => ['label' => 'Estimasi laba kotor', 'definition' => 'Pendapatan item dikurangi snapshot HPP item yang tersedia; bukan laba bersih.', 'unit' => 'IDR'],
            'tax_amount' => ['label' => 'Pajak', 'definition' => 'Jumlah tax_amount pada order paid. Pajak ditambahkan di atas penjualan bersih saat checkout, bukan bagian dari net_sales.', 'unit' => 'IDR'],
            'order_grand_total' => ['label' => 'Total tagihan order', 'definition' => 'Jumlah grand_total order paid: penjualan bersih + pajak + service + ongkir. Bukan penjualan bersih.', 'unit' => 'IDR'],
            'stock_on_hand' => ['label' => 'Stok teoritis', 'definition' => 'Nilai current_stock setelah pergerakan stok terakhir; bukan hasil stock count fisik.', 'unit' => 'item_unit'],
        ];

        return $keys ? array_intersect_key($all, array_flip($keys)) : $all;
    }
}
