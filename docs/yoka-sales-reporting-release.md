# Sales Reporting v2 — metric dictionary, evidence & release notes

Blueprint: `Yoka_POS_Sales_Reporting_Master_Blueprint_Claude.pdf`. Gap matrix: `yoka-sales-reporting-gap-analysis.md`.

## Metric dictionary v1.1 (`MetricDictionary`, dipakai laporan, ekspor, dan Tanya Yoka)

| Metrik | Definisi |
| --- | --- |
| `gross_item_sales` | `SUM(orders.subtotal)` order paid (dicocokkan dengan `SUM(order_items.subtotal)`; beda > Rp1 → peringatan) |
| `discounts` | `SUM(orders.discount_amount)` order paid |
| `net_sales` | gross − diskon. Pajak/service/ongkir **tidak** termasuk (ditambahkan di atas saat checkout) |
| `tax_amount`, `service_amount`, `delivery_fees` | dijumlah per kolom order |
| `order_grand_total` | `SUM(orders.grand_total)` = net + pajak + service + ongkir |
| `payment_collected` | order non-split: `grand_total` pada metode utama; order split: baris `order_payments`, kembalian dikurangkan dari baris tunai. Bukan settlement |
| `transactions` | `COUNT(order paid)` dalam `[start,end)` |
| `aov` | net_sales / transactions; N/A bila 0 |
| `gross_profit_estimate` | `SUM(item subtotal) − SUM(qty × cost_price)`; null bila tidak ada HPP; selalu disertai `cost_coverage_pct` |
| Penjualan bersih per item/kategori | `item.subtotal − order.discount × item.subtotal / order.subtotal` (alokasi proporsional; jumlah seluruh item = `net_sales`) |

Eligibility: `orders.status = 'paid'`. `refunded` dan `cancelled` dilaporkan terpisah (jumlah & nilai), tidak pernah masuk penjualan.
Zona waktu: tanggal diproses di zona waktu bisnis → batas `[start,end)` pada zona waktu DB. Tren jam/hari memakai zona waktu bisnis.

## Fitur

* **Ringkasan** `/reports/sales`: KPI, tren (hari/hari-minggu/jam/minggu/bulan), metode bayar, jam, kategori, order terbaru, estimasi laba kotor + cakupan HPP.
* **Analitik** `/reports/sales/analytics`: periode sebelumnya, rentang kustom, outlet lain; delta N/A bila pembanding 0; penanda periode berjalan vs penuh.
* **Breakdown** `/reports/sales/breakdown`: 9 dimensi × 5 metrik; kombinasi yang tidak dapat diatribusikan ditolak dengan alasan.
* **Produk** `/reports/sales/products`: Product Mix + HPP + item lama tanpa ID produk ditandai.
* **Riwayat Order** `/reports/sales/orders` (+ detail): 25/halaman, pencarian nomor, status, drill-down hari/jam, tenant & outlet scoped.
* **Ekspor** `reports.sales.export` / `reports.sales.pdf`: XLSX 6 sheet dan PDF dari dataset yang sama dengan layar; metadata periode, outlet, zona waktu, waktu pembuatan, versi metrik, hash filter. Setiap ekspor dicatat di log (`sales_report_export`, tanpa data pelanggan).
* **Tanya Yoka**: `reports.sales.ask` membuat referensi terenkripsi (30 menit, terikat user + bisnis); pertanyaan dibangun ulang di server dari filter allowlist.
* **Keamanan**: route `reports.*` kini `role:owner,admin`; outlet divalidasi; admin yang terikat outlet hanya melihat outletnya.
* **Rollback**: `FEATURE_REPORTS_V2=false` mengembalikan laporan penjualan lama (halaman, XLSX, PDF) tanpa deploy ulang kode.

## Keterbatasan yang jujur

* Refund/void hanya penuh per order (skema tidak mencatat refund parsial). Tidak ada tip, labor, settlement gateway, biaya payment, atau rekonsiliasi bank.
* Alokasi diskon ke item bersifat proporsional (order-level discount tidak punya baris per item).
* Tanya Yoka menerima kalimat pertanyaan yang dibangun server; angka yang dijawab berasal dari tool yang sama dengan laporan (diuji paritas), tetapi model memilih sendiri periode dari kalimat tersebut.
* Tidak ada cache hasil dan tidak ada antrian ekspor: ekspor XLSX dibatasi 5.000 order, PDF 500 order (ada keterangan terpotong). Rentang maksimal 400 hari.
* Zona waktu jam/hari memakai offset tetap pada awal rentang (tepat untuk Asia/Jakarta, yang tidak memakai DST).
* Indeks DB tidak ditambah: belum ada baseline `EXPLAIN` dari data produksi. Query memakai indeks `orders(business_id, created_at)` yang sudah ada.
* Hanya MySQL/MariaDB (`HOUR`, `WEEKDAY`, `DATE_ADD`).

## Verifikasi (lingkungan lokal, MariaDB 10.11)

* `php artisan test`: lihat laporan akhir untuk hitungan terbaru.
* QA browser: ringkasan, analitik (periode berjalan vs penuh), breakdown (total kategori × outlet = penjualan bersih Ringkasan), overflow tablet 768px diperiksa pada data showcase lokal. Produksi belum disentuh.
