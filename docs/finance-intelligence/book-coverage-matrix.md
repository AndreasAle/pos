# Matriks cakupan buku -> implementasi Nanda & Luna

Dibuat dari ekstraksi teks penuh ketiga PDF (PyMuPDF). Semua nomor halaman = **halaman PDF**.

| Buku | Halaman PDF | Teks terekstrak | Halaman tanpa teks |
|---|---|---|---|
| Pengantar Manajemen Keuangan (Matdio Siahaan, 2022) | 136 | 200 KB | 75, 91, 92, 93 (kemungkinan gambar/tabel, belum di-OCR) |
| Principles of Accounting Vol. 1: Financial Accounting (OpenStax) | 973 | 2,2 MB | 1, 2, 489 (sampul/kosong) |
| Principles of Accounting Vol. 2: Managerial Accounting (OpenStax) | 677 | 1,7 MB | 1, 2, 677 (sampul/kosong) |

**Kejujuran cakupan:** sekitar 1.800 halaman TIDAK dibaca kata per kata oleh model. Seluruh bab dan subbab (dari daftar isi PDF) dipetakan di bawah; bab yang diimplementasikan dirujuk pada halaman yang disebut; Bab I-IV Manajemen Keuangan dibaca langsung. Ringkasan konsep di `app/Services/PlatformFinance/Knowledge.php` ditulis ulang, bukan kutipan. Latihan, soal, dan kunci jawaban tidak dipetakan satu per satu.

Status: **Diimplementasikan** = kode deterministik + tes; **Sebagian**; **Konsep** = hanya pengetahuan yang bisa ditarik agen; **Tidak relevan** = dengan alasan bisnis.

## Principles of Accounting Vol. 1: Financial Accounting

| Bab | Judul (hlm.) | Subbab (hlm.) | Agen | Kemampuan | Implementasi | Tes | Status |
|---|---|---|---|---|---|---|---|
| FA 1 | Role of Accounting in Society (25) | 1.1 (26); 1.2 (28); 1.3 (31); 1.4 (36); 1.5 (44) | both | Membedakan akuntansi keuangan vs manajerial; pengguna laporan | Knowledge::financial_statements | - | Konsep |
| FA 2 | Introduction to Financial Statements (67) | 2.1 (68); 2.2 (81); 2.3 (85) | both | Menyusun dan membaca laba rugi, ekuitas, neraca, arus kas | Ledger::incomeStatement/balanceSheet; Knowledge accounting_equation | LedgerTest 12, 13 | Diimplementasikan |
| FA 3 | Analyzing and Recording Transactions (115) | 3.1 (116); 3.2 (123); 3.3 (128); 3.4 (133); 3.5 (137); 3.6 (160) | nanda | Persamaan akuntansi, jurnal double-entry, neraca saldo | Ledger::post/trialBalance; Transactions | LedgerTest 01-06 | Diimplementasikan |
| FA 4 | The Adjustment Process (203) | 4.1 (204); 4.2 (207); 4.3 (218); 4.4 (227); 4.5 (230) | nanda | Jurnal penyesuaian (akrual/deferral) | Knowledge::adjusting_entries | - | Sebagian: konsep ada, jurnal penyesuaian otomatis belum |
| FA 5 | Completing the Accounting Cycle (273) | 5.1 (273); 5.2 (284); 5.3 (286); 5.4 (297) | both | Jurnal penutup, current ratio, modal kerja | Ledger::closePeriod; Metrics::liquidity | LedgerTest 29 | Sebagian: kunci periode ada, jurnal penutup ke laba ditahan belum |
| FA 6 | Merchandising Transactions (343) | 6.1 (344); 6.2 (354); 6.3 (361); 6.4 (367); 6.5 (374); 6.6 (378); 6.7 (381) | nanda | Transaksi dagang/persediaan | - | - | Tidak relevan (Yoka jasa SaaS) |
| FA 7 | Accounting Information Systems (423) | 7.1 (424); 7.2 (435); 7.3 (445); 7.4 (454); 7.5 (461) | nanda | Sistem informasi akuntansi, buku pembantu | Bagan akun platform_fin_accounts | - | Sebagian |
| FA 8 | Fraud, Internal Controls, and Cash (495) | 8.1 (496); 8.2 (499); 8.3 (504); 8.4 (509); 8.5 (512); 8.6 (514); 8.7 (516) | nanda | Pengendalian internal, kas kecil, rekonsiliasi bank | Review, jurnal immutable, idempotensi, kunci periode | LedgerTest 10, posted_records, 30 | Diimplementasikan (rekonsiliasi berbasis temuan; impor mutasi bank belum) |
| FA 9 | Accounting for Receivables (537) | 9.1 (538); 9.2 (544); 9.3 (553); 9.4 (557); 9.5 (561); 9.6 (564); 9.7 (570) | nanda | Pengakuan pendapatan, piutang | Transactions::subscriptionPaid; Knowledge revenue_recognition | MetricsAndAccess midtrans_webhook | Sebagian: pendapatan diterima di muka belum otomatis |
| FA 10 | Inventory (603) | 10.1 (603); 10.2 (612); 10.3 (617); 10.4 (625); 10.5 (627) | nanda | Persediaan | - | - | Tidak relevan |
| FA 11 | Long-Term Assets (651) | 11.1 (652); 11.2 (656); 11.3 (659); 11.4 (670); 11.5 (672) | nanda | Aset tetap dan penyusutan | Knowledge::long_term_assets | - | Konsep; akun aset tetap belum ada |
| FA 12 | Current Liabilities (691) | 12.1 (692); 12.2 (700); 12.3 (708); 12.4 (716); 12.5 (720) | nanda | Kewajiban lancar, payroll | Akun 2000-2400; Knowledge current_liabilities/payroll | LedgerTest 06 | Sebagian |
| FA 13 | Long-Term Liabilities (749) | 13.1 (749); 13.2 (761); 13.3 (767); 13.4 (778) | luna | Kewajiban jangka panjang/obligasi | - | - | Tidak relevan saat ini |
| FA 14 | Corporation Accounting (795) | 14.1 (796); 14.2 (806); 14.3 (812); 14.4 (822); 14.5 (828) | nanda | Akuntansi korporasi/saham | Knowledge::owner_equity | - | Sebagian (badan usaha Yoka belum dipastikan PT) |
| FA 15 | Partnership Accounting (855) | 15.1 (856); 15.2 (861); 15.3 (864); 15.4 (866); 15.5 (871) | nanda | Persekutuan | Knowledge::owner_equity | - | Konsep |
| FA 16 | Statement of Cash Flows (883) | 16.1 (883); 16.2 (885); 16.3 (887); 16.4 (898); 16.5 (900); 16.6 (905) | both | Laporan arus kas (operasi/investasi/pendanaan) | Ledger::cashFlow (metode langsung) | LedgerTest 14, acceptance | Diimplementasikan |

## Principles of Accounting Vol. 2: Managerial Accounting

| Bab | Judul (hlm.) | Subbab (hlm.) | Agen | Kemampuan | Implementasi | Tes | Status |
|---|---|---|---|---|---|---|---|
| MA 1 | Accounting as a Tool for Managers (23) | 1.1 (24); 1.2 (32); 1.3 (39); 1.4 (48); 1.5 (52) | luna | Peran akuntansi manajerial | Knowledge | - | Konsep |
| MA 2 | Building Blocks of Managerial Accounting (77) | 2.1 (78); 2.2 (88); 2.3 (105) | luna | Perilaku biaya tetap/variabel/campuran | kolom behavior bagan akun; variable_costs di laba rugi | acceptance | Diimplementasikan |
| MA 3 | Cost-Volume-Profit Analysis (129) | 3.1 (130); 3.2 (136); 3.3 (147); 3.4 (154); 3.5 (158) | luna | Margin kontribusi, BEP, sensitivitas, MoS, DOL | Metrics::contributionMargin/breakEven/targetProfitUnits/marginOfSafety/operatingLeverage | MetricsAndAccess 15_16 | Diimplementasikan |
| MA 4 | Job Order Costing (181) | 4.1 (182); 4.2 (189); 4.3 (200); 4.4 (205); 4.5 (209); 4.6 (212); 4.7 (215); 4.8 (218) | luna | Job order costing | Knowledge::cost_systems | - | Relevansi terbatas |
| MA 5 | Process Costing (241) | 5.1 (241); 5.2 (251); 5.3 (253); 5.4 (257); 5.5 (259) | luna | Process costing | Knowledge::cost_systems | - | Relevansi terbatas |
| MA 6 | Activity-Based, Variable, and Absorption Costing (279) | 6.1 (280); 6.2 (284); 6.3 (286); 6.4 (293); 6.5 (296) | luna | ABC, variable vs absorption costing | Knowledge::cost_systems | - | Relevansi terbatas |
| MA 7 | Budgeting (327) | 7.1 (328); 7.2 (335); 7.3 (341); 7.4 (348); 7.5 (352) | luna | Anggaran operasi, keuangan, fleksibel | platform_fin_budgets; Metrics::budgetVsActual | MetricsAndAccess 17_18 | Sebagian: anggaran per akun ada; fleksibel/skenario belum |
| MA 8 | Standard Costs and Variances (377) | 8.1 (378); 8.2 (382); 8.3 (387); 8.4 (392); 8.5 (398) | luna | Biaya standar dan varians | Metrics::variance | MetricsAndAccess 17_18 | Diimplementasikan (varians anggaran) |
| MA 9 | Responsibility Accounting and Decentralization (423) | 9.1 (423); 9.2 (429); 9.3 (432); 9.4 (444) | luna | Akuntansi pertanggungjawaban | Knowledge::responsibility_accounting | - | Konsep; cost center belum |
| MA 10 | Short-Term Decision Making (471) | 10.1 (472); 10.2 (478); 10.3 (480); 10.4 (484); 10.5 (488); 10.6 (491) | luna | Keputusan jangka pendek, biaya relevan | Knowledge::relevant_costs | - | Konsep |
| MA 11 | Capital Budgeting Decisions (517) | 11.1 (518); 11.2 (521); 11.3 (527); 11.4 (535); 11.5 (543) | luna | Payback, ARR, nilai waktu uang, NPV | Metrics::paybackPeriod/presentValue/netPresentValue | MetricsAndAccess capital_budgeting | Diimplementasikan |
| MA 12 | Balanced Scorecard and Other Performance Measures (565) | 12.1 (566); 12.2 (571); 12.3 (574); 12.4 (582) | luna | Balanced scorecard, ROI, RI, EVA | Knowledge::performance_measures | - | Konsep |
| MA 13 | Sustainability Reporting (605) | 13.1 (606); 13.2 (621); 13.3 (626); 13.4 (630) | luna | Pelaporan keberlanjutan | - | - | Tidak relevan saat ini |

## Pengantar Manajemen Keuangan

| Bab | Judul | Hlm. PDF | Agen | Implementasi | Status |
|---|---|---|---|---|---|
| MK I | Peranan Manajemen Keuangan | 11-14 | luna | Knowledge::financial_management_role | Konsep |
| MK II | Rasio-Rasio Keuangan | 15-17 | luna | Knowledge::ratio_analysis; Metrics::liquidity | Sebagian |
| MK III | Laporan Keuangan, Pajak, Arus Kas | 18-23 | both | Laporan Ledger; Knowledge accrual_vs_cash | Diimplementasikan |
| MK IV | Analisis Laporan Keuangan | 24-29 | luna | Knowledge::ratio_analysis | Konsep |
| MK V | Nilai Waktu Uang | 30-35 | luna | Metrics::presentValue/netPresentValue | Diimplementasikan |
| MK VI | Penilaian Surat Berharga | 36-40 | luna | - | Tidak relevan saat ini |
| MK VII | Kebijakan Modal Kerja | 41-42 | luna | Metrics::liquidity; Knowledge working_capital | Sebagian |
| MK VIII | Sumber dan Penggunaan Modal Kerja | 43-45 | luna | Ledger::cashFlow | Sebagian |
| MK IX | Struktur Modal | 46-52 | luna | Knowledge::capital_structure | Konsep |
| MK X | Biaya Modal | 53-55 | luna | Knowledge::capital_structure | Konsep |
| MK XI | Return dan Risiko | 56-61 | luna | Knowledge::capital_structure | Konsep |
| MK XII | Manajemen Kas | 62-64 | luna | Metrics::runway; Ledger::cashBalance | Diimplementasikan |
| MK XIII | Manajemen Piutang | 65-67 | both | Knowledge::receivables | Konsep |
| MK XIV | Manajemen Persediaan | 68-71 | luna | - | Tidak relevan (SaaS) |
| MK XV | Break Even Point | 72-76 | luna | Metrics::breakEven | Diimplementasikan |

## Konsep penting Yoka yang diuji langsung

Daftar lengkap 38 konsep (sumber, rumus, tool) ada di `Knowledge::CONCEPTS`.

| Konsep | Sumber | Tes |
|---|---|---|
| Modal pemilik bukan pendapatan | FA 2.2, 3.2, 14.4 | LedgerTest 05 |
| Biaya ditalangi pemilik tetap beban usaha | FA 3.1 (entitas terpisah) | LedgerTest 06 |
| Debit = kredit; jurnal tidak seimbang ditolak | FA 3.5 | LedgerTest 01 |
| Neraca saldo seimbang | FA 3.6 | LedgerTest 04 |
| Koreksi lewat jurnal pembalik, bukan edit | FA 8.2-8.3 | LedgerTest 11, posted_records |
| Midtrans = akun penampung; pencairan bukan pendapatan | FA 3.5, 8.6 | LedgerTest 07_09, acceptance |
| Arus kas tiga aktivitas; pindah dana diabaikan | FA 16.2, 16.6 | LedgerTest 14 |
| BEP, margin kontribusi, MoS, DOL | MA 3.1-3.5; MK XV | MetricsAndAccess 15_16 |
| Varians anggaran F/U | MA 7.5, 8.5 | MetricsAndAccess 17_18 |
| Payback dan NPV | MA 11.2-11.4; MK V | capital_budgeting |
