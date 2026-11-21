# 10 — Ringkasan 2.1

**Status: IMPLEMENTED WITH LIMITATIONS**

## Sebelum → sesudah (dari percakapan Andopos)
| Sebelum | Sesudah |
|---|---|
| "Halo kak Andopos, saya Adit … coba gratis 7 hari di yokapos.com/register" | "Selamat pagi Pak/Bu. Saya Adit, asisten AI dari Yoka POS. … Izin tanya, kalau warung lagi ramai, pesanan … dicatat di nota atau sudah pakai aplikasi?" |
| 2–3 pesan per giliran | 1 pesan per giliran |
| "servisnya dari kami tim Indonesia langsung" | Klaim dibuang; Adit diarahkan menanyakan apa persisnya masalah layanan dulu |
| Dua pertanyaan dalam satu balasan | Maksimal satu |
| "oke kak, siap." | Penutupan hangat + rangkuman kebutuhan, tanpa pertanyaan/link; tercatat "akan menghubungi lagi" |
| Printer hanya dijawab teknis | Dijawab + dicatat sebagai minat; bundel → approval |

## Flag (`config/yoka_sales.php` → `bd21`)
`ADIT_OPENING_STRATEGY_ENABLED`, `ADIT_CONVERSATION_STRATEGY_ENABLED`, `ADIT_RESPONSE_QUALITY_ENABLED`, `ADIT_ONE_MESSAGE_PER_TURN`, `ADIT_INDUSTRY_PLAYBOOK_ENABLED`, `ADIT_HARDWARE_OPPORTUNITY_ENABLED` (default aktif; hanya mengubah cara menulis, bukan izin), `ADIT_INBOUND_COALESCE_SECONDS` (default 0 = mati).
Rollback cepat: set flag ke `false` di `.env` lalu `php artisan config:cache`. Rollback penuh: revert commit (tidak ada migration).

## Tidak diubah
Approval Setujui & kirim, otoritas harga, proposal berversi, opt-out, Midtrans, alur CS pelanggan terdaftar.

## Keterbatasan
- Gaya kalimat tetap ditulis model; aturan strategi hanya mengarahkan, penyaring hanya membuang (belum regenerasi).
- Deteksi tujuan/keberatan berbasis pola kata.
- Belum ada tombol umpan balik admin per balasan dan katalog perangkat.
- Belum diuji dengan model dan gateway sungguhan. Perlu pilot: kirim beberapa draf ke nomor uji, baca hasilnya, lalu sesuaikan.

## Keputusan pemilik
1. Jam layanan support resmi (agar Adit bisa menyebutnya).
2. Mau menjual printer/bundel? Bila ya: daftar model yang sudah dites, harga, dan garansi.
3. Nyalakan jendela gabung pesan (mis. 8 detik)?
