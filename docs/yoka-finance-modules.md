# Modul Keuangan: Pajak, Retur, Biaya, Laba Rugi, Pembelian, Hutang, Piutang

Dibangun di atas lapisan metrik penjualan (`SalesMetricsService`) sehingga angka penjualan, diskon, HPP, dan pajak sama dengan Laporan Penjualan dan Tanya Yoka. Semua halaman owner/admin saja, tenant- dan outlet-scoped.

## Standar yang dipakai
Rujukan: struktur laporan SAK EMKM (laba rugi bertingkat: penjualan bersih → HPP → laba kotor → beban operasional → laba operasional → beban lain → laba sebelum pajak), penomoran akun 6xxx/7xxx, aging 0/1-30/31-60/61-90/>90 hari, dan prinsip dokumen tidak dihapus melainkan di-void agar ada jejak audit. Ini **laporan manajemen**, bukan laporan keuangan teraudit, dan bukan jurnal berpasangan (double-entry).

## Basis dan aturan
| Topik | Aturan |
| --- | --- |
| Penjualan bersih | `subtotal − diskon` order paid. Pajak, service, ongkir **bukan** pendapatan (liabilitas/pass-through) dan ditampilkan sebagai memo |
| HPP | `SUM(qty × cost_price snapshot)` item terjual; peringatan bila cakupan < 100% atau 0% |
| Pembelian stok | Persediaan, **bukan** beban; menambah stok bahan baku dan harga rata-rata tertimbang lewat `InventoryService::addStock` |
| Beban | Basis kas per `expense_date`; kategori kelompok `operating` → laba operasional, `other_expense` → beban lain, `tax` (2105 setoran pajak) → **tidak** masuk laba rugi, mengurangi saldo pajak |
| Biaya umum (tanpa outlet) | Hanya dihitung bila seluruh outlet ada dalam cakupan filter |
| Pajak penghasilan | Tidak dihitung |
| Void/batal | Biaya dan piutang di-void; pembelian dibatalkan hanya bila belum ada pembayaran (stok ditarik kembali) |
| Nomor dokumen | `BY-/PB-/PT-yymmdd-NNN`, unik per bisnis |

## Halaman
* Laporan: `reports.tax`, `reports.returns`, `reports.profit-loss` (banding periode sebelumnya, XLSX, PDF untuk laba rugi).
* Keuangan: `expenses.*`, `expense-categories.*` (14 akun awal, bisa ditambah), `suppliers.*`, `purchases.*` (bayar bertahap), `payables.index` (aging per supplier), `receivables.*` (aging, penerimaan bertahap).

## Keterbatasan
* Tidak ada jurnal umum/neraca/arus kas; tidak ada penyusutan aset, bunga pinjaman, atau modal pemilik.
* Piutang dicatat manual: kasir belum mendukung penjualan kredit. Hutang berasal dari pembelian.
* Retur hanya refund/void penuh per order; periode retur memakai tanggal transaksi asli.
* Pajak: rekap yang dipungut POS, bukan SPT/bukti setor; setoran hanya yang dicatat sebagai biaya akun 2105.
* Pembatalan pembelian menarik stok tetapi tidak mengembalikan harga rata-rata ke nilai sebelumnya.
* Lampiran bukti (foto kuitansi) belum ada.
