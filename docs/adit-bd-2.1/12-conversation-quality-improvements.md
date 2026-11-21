# 12 — Perbaikan kualitas percakapan

Pembuka (`OpeningStrategy`, `IndustryPlaybook.opening_question/audience`), contoh pecel lele:
> Selamat pagi Pak/Bu, izin kenalan ya. Saya Adit, asisten AI dari Yoka POS.
>
> Kami punya aplikasi kasir untuk usaha kuliner. Mau tanya sedikit, kalau warung sedang ramai, biasanya pesanan masih dicatat pakai kertas atau sudah pakai aplikasi?

Nama bisnis tidak lagi disisipkan; tanpa link, satu pertanyaan.

`ResponseQuality::review` (deterministik, tanpa panggilan model kedua):
1. Buang kalimat dengan klaim layanan tak terverifikasi (kecuali menyebut jam resmi dari config) dan URL yang tidak terdaftar.
2. Buang link trial bila strategi tidak mengizinkan (mencegah ajakan trial berulang).
3. Buang pertanyaan yang ≥ 80% mirip pertanyaan di 6 pesan keluar terakhir (`repeated_question`), lalu batasi satu pertanyaan.
4. Rapikan akhir pesan: hapus kata sambung/koma menggantung, beri tanda titik, huruf besar awal.
5. Bila hasil kosong, atau pertanyaan harga kehilangan harganya → **fallback terverifikasi** per tujuan (harga dari `subscription_plans`, printer dari fakta cetak, penutupan sopan, pertanyaan klarifikasi trust, link trial, "saya teruskan ke tim"). Tujuan lain tanpa fallback → tidak membalas, tetap tercatat di kanban.

Regenerasi lewat model **tidak** dibuat (sengaja: biaya dan waktu di shared hosting); fallback dipilih sebagai pengganti yang deterministik.
Satu pesan per giliran, penutupan, dan opt-out tetap seperti 2.1.
