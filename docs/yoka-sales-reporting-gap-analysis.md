# Sales Reporting — audit & gap matrix (12 Nov 2026)

Source: `Yoka_POS_Sales_Reporting_Master_Blueprint_Claude.pdf`, audited against `main` @ 2e4cf6a.

## Temuan audit (kode nyata)

| Temuan | Dampak | Status |
| --- | --- | --- |
| `ReportService::salesReport` melabeli `SUM(grand_total)` sebagai "Omzet". `grand_total` = subtotal − diskon + **pajak + service + ongkir** | Omzet di laporan lebih besar dari `net_sales` Tanya Yoka | Diperbaiki: laporan v2 memakai `net_sales`; v1 tetap ada di balik flag |
| Rincian pembayaran legacy memakai `orders.payment_method` + `grand_total` | Order split hanya dihitung pada metode pertama | Diperbaiki: baris `order_payments` + kembalian dikurangkan dari tunai |
| Filter tanggal `DATE(created_at)` di tiap query | Tidak memakai index, zona waktu bisnis diabaikan | Diperbaiki: jendela `[start,end)` dari zona waktu bisnis |
| Route `reports.*` tanpa `role:` middleware | Kasir bisa membuka laporan keuangan | Diperbaiki: owner/admin saja |
| `outlet_id` dari request tidak divalidasi kepemilikannya | Parameter bisa menunjuk outlet tenant lain (hasil kosong, bukan bocor, karena `business_id` ikut di-filter) | Diperbaiki: validasi whitelist outlet |
| Ekspor `exportSales()` di service masih `abort(501)` | Dead code | Dibiarkan; ekspor nyata ada di controller |
| Yoka `payments()` tidak mengurangi kembalian dari tunai | Tunai split bisa lebih besar dari yang diterima toko | Diperbaiki lewat service bersama |

## Matriks gap

| Kebutuhan blueprint | Status | Catatan |
| --- | --- | --- |
| Metric layer tunggal | **Baru** (`app/Services/Reporting`) | Dipakai laporan v2, ekspor, dan Tanya Yoka (`sales`, `payments`) |
| Metric dictionary + versi | **Diperbarui** v1.1 | `tax_amount`, `order_grand_total`, definisi pembayaran split |
| Summary, tren hari/minggu/jam/bulan | Baru | Zona waktu bisnis |
| Analitik banding periode / outlet | Baru | Delta N/A bila pembanding = 0 |
| Breakdown dua dimensi | Baru | Kategori, produk, outlet, kasir, metode bayar, hari, jam, tipe layanan |
| Product Mix | Baru | Diskon order dialokasikan proporsional terhadap subtotal item; HPP + cakupan |
| Riwayat order + detail | Baru | Tenant & outlet scoped |
| Ekspor XLSX/PDF paritas | Perbaikan | Filter, total, metadata sama dengan layar |
| Handoff Tanya Yoka | Baru | Referensi terenkripsi, di-autentikasi ulang di server |
| Refund/void | **Parsial** | Skema hanya mengenal refund/cancel **seluruh order**; dilaporkan terpisah, tidak ada refund parsial |
| Tip, labor, settlement gateway, biaya payment, rekonsiliasi bank | **Diblokir data** | Tidak ada di skema; tidak diklaim |
| Dining option | Tersedia | `orders.order_type` (dine_in/takeaway/delivery) |
| Cache hasil | Tidak dibuat | Tanpa invalidasi aman per-tenant, lebih baik tanpa cache |
| Antrian ekspor besar | Tidak dibuat | Dibatasi baris; lihat keterbatasan |

## Keputusan domain (kode menang atas PDF)

* Pajak/service/ongkir **ditambahkan di atas** penjualan bersih saat checkout (`PosOrderService`), jadi fixture Order A di PDF benar apa adanya: net 90.000, tagihan 99.000.
* Penjualan kotor tingkat order = `orders.subtotal` (sama dengan Tanya Yoka); jumlah `order_items.subtotal` dicocokkan dan memberi peringatan bila berbeda.
* Angka nol pada grafik hanya diisi ketika ada minimal satu transaksi di rentang; tanpa transaksi tidak ada grafik.
