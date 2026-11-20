# Keuangan Yoka: audit, status V1, dan rencana V2

Tanggal: 2026-11-20. Lingkup: keuangan **usaha Yoka sendiri** (platform), terpisah dari data toko klien (`orders`, `expenses`, `purchases`, dll. milik tenant).

## 1. Kondisi sebelum pekerjaan ini (audit repositori)

| Area | Temuan | Klasifikasi |
|---|---|---|
| Buku besar platform | Tidak ada. Hanya `platform_ai_records` jenis `finance` (catatan bebas tanpa debit/kredit). | Missing |
| Pendapatan | `AgentBrain::businessMetrics` menjumlah `business_subscriptions.amount` per bulan (tunai masuk, bukan MRR). | Partially implemented |
| Midtrans | Webhook `MidtransController::callback` memverifikasi signature, mengunci baris, dan idempoten untuk aktivasi langganan. Tidak mencatat ke akuntansi. Tidak ada data settlement/fee. | Production-ready (pembayaran) / Missing (akuntansi) |
| Biaya AI | `PlatformAiRun.estimated_cost_usd` dari token. Estimasi, bukan tagihan. | Implemented (estimasi) |
| Nanda / Luna | Prompt + tool baca umum (`business_metrics`, `daily_report`, `funnel`, `list_records`). Tidak ada laporan keuangan, rumus, atau sumber buku. | Partially implemented |
| Peran admin | `PlatformAdmin::canViewFinancials()` = super_admin, finance. | Production-ready |
| Tes | Tidak ada tes keuangan platform. | Missing |

## 2. Yang dibangun sekarang (V1 → fondasi V2)

| Kemampuan | File | Tes | Status |
|---|---|---|---|
| Bagan akun 29 akun (kas, bank, **Saldo Midtrans** sebagai clearing, piutang, dibayar di muka, hutang, pinjaman/talangan pemilik, pajak, pendapatan diterima di muka, modal, prive, laba ditahan, pendapatan, refund, biaya pokok layanan, marketing, operasional) dengan perilaku biaya | migrasi `2026_11_20_000001_create_platform_finance_ledger` | LedgerTest | Implemented & tested |
| Jurnal double-entry: seimbang, satu sisi per baris, integer sen, transaksi DB, idempotency key unik, jurnal pembalik, kunci periode, jurnal/baris tidak bisa diubah/dihapus | `Services/PlatformFinance/Ledger`, `Models/PlatformFinance/*` | LedgerTest 01-04, 10, 11, 29, posted_records | Implemented & tested |
| Klasifikasi transaksi: beban (dari kas/bank/Midtrans atau ditalangi pemilik sebagai modal/pinjaman/talangan), modal, prive, pindah dana, pendapatan lain, refund, pencairan Midtrans + fee | `Transactions` | LedgerTest 05-07, 11 | Implemented & tested |
| Midtrans → buku besar otomatis, idempoten, tidak bisa menggagalkan aktivasi; backfill `php artisan yoka:finance-sync-midtrans` | `MidtransController`, `FinanceSyncMidtrans` | midtrans_webhook (webhook asli dua kali = 1 jurnal) | Implemented & tested |
| Laporan: laba rugi multi-step, neraca (seimbang dengan laba berjalan), arus kas metode langsung, neraca saldo, kas & bank | `Ledger` | LedgerTest 12-14, acceptance | Implemented & tested |
| Pemeriksaan Nanda: neraca saldo, pembayaran belum terjurnal, Midtrans belum direkonsiliasi/negatif, tanpa bukti, kemungkinan dobel, estimasi biaya AI vs invoice, talangan pemilik | `Review` | LedgerTest 28, 30 | Implemented & tested |
| Rumus deterministik: margin kontribusi, BEP, target laba, MoS, DOL, varians, payback, PV, NPV, likuiditas, anggaran vs aktual | `Metrics` | MetricsAndAccess 15-18, capital_budgeting | Implemented & tested |
| Metrik SaaS: MRR/ARR/ARPA, CAC, churn, LTV, runway, CAC payback dengan status `insufficient_data` | `Metrics` | MetricsAndAccess 19-23 | Implemented & tested |
| Pengetahuan 38 konsep dari 3 buku dengan halaman sumber, ditarik per pertanyaan | `Knowledge` | knowledge_retrieval | Implemented & tested |
| Tool agen **hanya baca**: `finance_ledger`, `finance_metrics`, `finance_knowledge` | `AgentFinanceTools`, `AgentBrain` | 24, 26, 27 | Implemented & tested |
| Halaman **Keuangan Yoka** (super admin/finance): ringkasan, catat transaksi, temuan Nanda, laba rugi, anggaran, jurnal + pembalik, tutup periode | `Platform/FinanceController`, `platform/finance.blade.php` | 24b | Implemented & tested |
| Skenario V2 §19 (modal 2jt, Meta 300rb, hosting 150rb, langganan 90rb via Midtrans, fee, pencairan, anggaran bulan depan) | | acceptance | Tested |

## 3. Spesifikasi V2: status (per 2026-11-20, semua butir selesai)

| Kebutuhan V2 | Status | Implementasi |
|---|---|---|
| Double-entry, idempotensi, pembalik, kunci periode, immutable | Done | `Ledger`, model `Entry`/`Line` tidak bisa diubah/dihapus |
| Bagan akun korporat | Done | 32 akun, termasuk aset tetap 1500, akumulasi penyusutan 1510 (contra, mengurangi aset di neraca), beban penyusutan 7300 |
| Cost center | Done | Kolom `cost_center` di baris jurnal, biaya, aset (platform, ai, cs, marketing, umum); pembalik ikut membalik cost center; laporan beban per cost center |
| Treasury | Done | Posisi kas, clearing Midtrans, pindah dana, modal, talangan; rekonsiliasi per transaksi (`Reconciliation`); peramalan kas (`CashForecast`) |
| Pendapatan diterima di muka | Done | Masa layanan > 1 bulan: Dr 1200 / Cr 2400 lalu diakui merata per bulan (`RevenueRecognition`, jadwal `platform_fin_revenue_schedules`, cron harian `yoka:finance-recognize-revenue`). Paket bulanan tidak berubah. MRR memakai nilai bulanan |
| Siklus biaya + hutang usaha | Done | Submitted, Approved+Posted, Rejected; tagihan vs pembayaran; umur hutang; status rekonsiliasi lewat pencocokan mutasi |
| Anggaran, versi, skenario, rolling forecast | Done | `BudgetPlanner`: proyeksi deterministik dari data 3 bulan terakhir, skenario konservatif/dasar/growth, versi draf/aktif/arsip/ditolak, persetujuan menjadikan anggaran aktif, rolling forecast 12 bulan (aktual + anggaran/proyeksi) |
| Metrik SaaS | Done | MRR/ARR/ARPA, CAC, churn, LTV, runway, CAC payback, **NRR, GRR, revenue churn, expansion/contraction** |
| Perbandingan | Done | MoM dan YTD vs tahun lalu (persentase tidak dikarang bila pembanding nol) |
| Aset tetap | Done | Register aset, penyusutan garis lurus mulai bulan setelah perolehan, idempoten, otomatis bulanan lewat cron |
| Close bulanan | Done | 15 cek (merah memblokir: periode berjalan, neraca saldo, biaya tertunda, Midtrans belum terjurnal/negatif, pendapatan belum diakui, penyusutan belum diposting); buka ulang terkontrol |
| Peran & matriks persetujuan | Done | `super_admin` (tanpa batas), `finance` (batas `YOKA_FINANCE_APPROVAL_LIMIT`, default Rp5 jt, tidak boleh menyetujui pengajuan sendiri), `finance_auditor` (lihat + ekspor saja) |
| Audit trail | Done | Semua aksi tulis dan ekspor tercatat append-only |
| Tool agen terkendali | Done | Nanda: draf biaya. Luna: draf anggaran (maks 3/hari). Laporan baca: forecast, skenario, rolling, retensi, perbandingan, cost center, aset, pendapatan di muka. Tidak ada tool posting/pembayaran/hapus |
| UI per modul | Done | Satu halaman dengan 7 tab: Ringkasan, Transaksi & tagihan, Rekonsiliasi, Anggaran & forecast, Aset & pendapatan, Tutup buku, Jurnal/laporan/audit |
| Ekspor & drill-down | Done | 10 laporan CSV (UTF-8, Excel), formula injection dinetralkan |

Batas yang sengaja tetap ada: pajak tidak dihitung otomatis (butuh konsultan); impor mutasi lewat CSV, bukan koneksi langsung ke bank (butuh kredensial bank/API resmi); tidak ada konsolidasi multi-entitas karena Yoka satu entitas.

## 4. Hal yang butuh keputusan pemilik

1. **Bentuk badan usaha Yoka** (perorangan/CV/PT). Ini mempengaruhi akun ekuitas dan pajak. Saat ini memakai Modal Pemilik + Prive (usaha perorangan).
2. **Kebijakan pengakuan pendapatan** untuk paket lebih dari satu bulan, sebelum paket tahunan dijual.
3. **Saldo awal**: tanggal mulai pembukuan dan saldo kas/bank awal (dicatat sebagai setoran modal atau jurnal saldo awal).
4. Pajak: tarif dan kewajiban (PPh Final UMKM, PPN bila PKP) harus dicek ke konsultan atau sumber resmi; buku ajar bukan pengganti regulasi.

## 5. Kamus metrik

| Metrik | Rumus | Sumber data | Kecualikan | Bila data kurang |
|---|---|---|---|---|
| MRR | Σ harga bulanan langganan `active`, sudah dibayar, `ends_at` ≥ tanggal | business_subscriptions | trial, pending, kedaluwarsa | `insufficient_data` bila 0 pelanggan berbayar |
| ARR | MRR x 12 | MRR | | ikut MRR |
| ARPA | MRR / jumlah toko berbayar | MRR | | ikut MRR |
| CAC | biaya akun grup marketing / toko berbayar pertama kali pada periode | buku besar + subscriptions | biaya non-marketing | tanpa biaya atau tanpa pelanggan baru = tidak dihitung |
| Churn pelanggan | pelanggan berbayar awal periode yang tidak aktif di akhir / pelanggan awal | subscriptions | trial | 0 pelanggan awal = tidak dihitung |
| LTV | ARPA x margin kotor % / churn | MRR, laba rugi, churn | | churn 0 atau pendapatan belum tercatat = tidak dihitung. **Bukan aset.** |
| Burn bersih | -(arus kas operasi + investasi) per bulan | buku besar (kas + bank) | saldo Midtrans belum cair | arus kas positif = tidak ada burn |
| Runway | (kas + bank) / burn bersih | buku besar | saldo Midtrans | tidak ada burn = tidak dihitung |
| CAC payback | CAC / (ARPA x margin kotor %) | gabungan | | salah satu belum terhitung = tidak dihitung |

Arus kas masuk bulanan **tidak sama** dengan MRR. Estimasi biaya AI dari telemetri **bukan** beban sampai invoice dicatat.

## 6. Deploy (setelah disetujui)

1. `cd ~/yokapos_app && ./deploy.sh`. Skrip mem-backup database lalu menjalankan migrasi; migrasi menambah 14 tabel baru; satu-satunya perubahan tabel lama adalah **menambah** nilai `finance_auditor` ke enum `platform_admins.role` (data admin tidak berubah).
2. `php artisan yoka:finance-sync-midtrans --dry-run`, lalu tanpa `--dry-run` untuk mencatat langganan Midtrans yang sudah dibayar.
3. Buka **Keuangan Yoka** di platform, lalu catat saldo awal (setoran modal) dan biaya bulan ini.
4. Rollback: tabel baru bisa di-drop lewat `migrate:rollback --step=1` setelah backup. Kode webhook hanya menambah pencatatan dalam `try/catch`, jadi aktivasi langganan tidak terpengaruh.

## 7. Tes (per 2026-11-20)

`tests/Feature/PlatformFinance`: LedgerTest (18), MetricsAndAccessTest (15), ExpenseWorkflowTest (11), MonthEndCloseTest (6), PayablesTest (6), ReconciliationTest (8), FinanceV2CompletionTest (11) = **75 tes, semua lulus**. Full suite 774 tes lulus. Migrasi diuji `migrate:fresh`, `rollback --step=5`, lalu `migrate` lagi di database uji lokal. Tidak ada data produksi yang disentuh.
