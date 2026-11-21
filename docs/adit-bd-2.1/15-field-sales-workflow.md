# 15 — Alur penjualan lapangan

Founder mengisi dan memverifikasi lead di spreadsheet → Adit mengkualifikasi dan mengedukasi → prospek **secara eksplisit** setuju didatangi / minta demo langsung / ingin lihat perangkat langsung → founder datang dan onboarding.

- Deteksi: `FieldVisit::CONSENT` (mis. "boleh kak, datang aja ke warung", "kapan bisa demo ke sini", "mau lihat printernya langsung"). Balasan biasa, tanya harga, atau skor tinggi **tidak** dihitung.
- Tahap baru `ready_for_field_visit` di `SalesStateMachine` (tanpa migration; kolom `sales_stage` sudah ada).
- Kartu `field_visit_brief` di Tim AI (satu per prospek, diperbarui bila ada sinyal baru): identitas usaha, cara catat sekarang, masalah, keberatan, minat perangkat, pernah tanya harga, kutipan persetujuan, demo yang disarankan, hal yang belum diketahui.
- Adit hanya bilang sudah diteruskan ke tim; tidak menyebut jam, tanggal, atau siapa yang datang. Eskalasi dicatat sebelum Adit boleh bilang "diteruskan".
- Opt-out mengalahkan semuanya: tidak ada brief untuk nomor yang berhenti.
