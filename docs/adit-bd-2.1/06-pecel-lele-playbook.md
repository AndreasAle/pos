# 06 — Playbook pecel lele (dan segmen lain)

`IndustryPlaybook::SEGMENTS['pecel_lele']` cocok bila jenis/nama/catatan mengandung: pecel, lele, penyetan, penyet, lalapan.

- Topik pembuka: pencatatan pesanan makan di tempat dan bungkus saat ramai.
- Topik discovery (dipilih satu bila relevan, bukan kuesioner): cara catat pesanan, makan di tempat vs bungkus, jam ramai, tutup kas dan rekap harian, stok lele/ayam, perlu printer atau tidak.

Pemetaan masalah → fitur yang benar-benar ada (`VALUE_MAP`):
| Masalah | Fitur |
|---|---|
| kasir ramai | kasir/POS login PIN di tablet, QR Meja |
| laporan | laporan penjualan, produk terlaris, shift, estimasi profit, laba rugi, ekspor Excel/PDF |
| margin | estimasi profit dan laba rugi; bergantung harga pokok/resep yang diisi |
| stok | stok bahan baku, resep, laporan inventory; bergantung data yang diisi |
| selisih uang | shift kasir, Audit Log |
| pesanan dapur | layar dapur |
| kertas | transaksi tercatat digital |

Tidak ada klaim pemotongan stok otomatis per bahan di luar fitur resep yang ada, dan tidak ada angka penghematan.
Segmen lain yang sudah ada: warung makan (bakso, mie ayam, geprek, dll.), kafe, resto, toko. Segmen baru cukup menambah entri di `SEGMENTS`.
