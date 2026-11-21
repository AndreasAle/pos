# 14 — Katalog perangkat dan bundel

`config/yoka_hardware.php` + `Sales\HardwareCatalog`:
- **MP-58N** (printer thermal 58mm, Bluetooth/USB): `status=candidate`, `compatibility=unverified`, harga beli referensi Rp166.000 (internal, tidak masuk prompt), harga jual null.
- **Yoka POS Starter Kit**: `status=draft`, usulan Rp349.000 di awal (printer + 1 bulan Starter), perpanjangan sesuai harga Starter resmi. Syarat (ongkir, instalasi, garansi, retur, refund, pajak) dan pembagian pendapatan perangkat vs langganan masih kosong.

Aturan:
- Adit hanya boleh menyebut harga perangkat/bundel bila item `approved` + `verified` + harga jual terisi, dan bundel `approved` dengan **semua** syarat terisi. Saat ini tidak ada → Adit bilang belum ada perangkat resmi.
- "Dapat printer?" → dijawab, dicatat sebagai minat, tanpa proposal.
- "Aplikasi sama printer sekalian berapa?" → tanpa bundel resmi: proposal `commercial_proposal` jenis `hardware_quotation` (kandidat item, draf harga, syarat yang belum ada) untuk super admin. Dengan bundel resmi: STANDARD_OFFER dengan harga persis.
- Penjaga harga mengizinkan harga perangkat/bundel resmi saja.
- Proposal yang disetujui tidak membuat pembayaran atau langganan otomatis.

Untuk mengaktifkan: tes MP-58N dengan Yoka POS (print browser + RawBT), isi harga jual, garansi, stok, syarat bundel, dan pembagian pendapatan, lalu ubah status ke `approved` / `verified`.
