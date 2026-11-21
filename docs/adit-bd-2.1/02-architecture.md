# 02 — Arsitektur 2.1 (tambahan ke BD 2.0)

Kelas baru di `app/Services/PlatformAgents/Sales/`:
- `OpeningStrategy`: identitas kontak (`personName`), salam sesuai jam WIB, kategori pembuka, follow-up.
- `IndustryPlaybook`: segmen (pecel lele, warung makan, kafe, resto, toko) + pemetaan masalah → fitur terverifikasi.
- `ConversationStrategy`: tujuan balasan, boleh bertanya atau tidak, kapan link trial, jenis penutupan, minat/bundel perangkat.
- `ResponseQuality`: pemeriksa balasan prospek sebelum dikirim (deterministik).

Diubah:
- `SalesOrchestrator::onInbound` → memanggil strategi, mencatat minat perangkat dan hasil penutupan, menambah aturan ke konteks prompt, mengembalikan `strategy`.
- `DraftWhatsAppReply` → aturan prompt satu pesan untuk prospek, menggabungkan bubble, menjalankan `ResponseQuality`, mencatat event `quality`, jendela gabung pesan opsional.
- `ProspectDesk` → pembuka/follow-up lewat `OpeningStrategy`; konteks tidak memakai nama bisnis sebagai nama orang.
- `CommercialPolicy` → permintaan bundel aplikasi + perangkat = NEEDS_HUMAN_APPROVAL; pola "kalau Rp50 ribu bisa?" dikenali.
- `CommercialApproval` → syarat proposal memuat `items`.
- `SalesMetrics` + halaman Prospek → minat printer, jumlah balasan yang dicek dan jenis perbaikannya.

Tanpa migration baru (memakai kolom `intelligence` dan tabel event BD 2.0).
Pelanggan terdaftar (bukan prospek) tetap memakai alur CS lama (multi-bubble) agar tidak mengubah perilaku yang belum diuji ulang.
