# 08 — Kontrol kualitas balasan

`ResponseQuality::review` berjalan untuk balasan ke prospek, setelah penjaga BD 2.0, tanpa panggilan model tambahan.

| Temuan | Tindakan |
|---|---|
| unverified_support_claim | kalimat dibuang |
| unverified_url | kalimat dibuang |
| trial_cta_out_of_place | kalimat link trial dibuang bila strategi tidak mengizinkan |
| extra_question | pertanyaan kedua dst., atau semua pertanyaan saat dilarang, dibuang |
| business_name_as_person | "kak Andopos" → "kak" |
| filler | "paham banget", "tenang aja", "pastinya", "wah" di awal dihapus |
| too_long (> 600 karakter), kak_overuse (> 2) | hanya dicatat |

Setiap balasan yang diperiksa dicatat sebagai event `quality` (temuan + tujuan + message_id) dan dirangkum di halaman Prospek Adit.

Belum dibuat: regenerasi otomatis lewat model, evaluasi model sampel, dan tombol umpan balik admin per balasan. Itu langkah berikutnya bila data event `quality` sudah cukup.
