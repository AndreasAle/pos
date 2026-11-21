# 17 — Kesiapan rilis

| Cek | Hasil |
|---|---|
| Suite penuh `phpunit` | **OK (837 tests, 8745 assertions)** |
| Tes terarah Adit (`AditReadiness`, `AditConversationQuality`, `AditBusinessDevelopment`, `ProspectDesk`) | OK (55 tests) |
| `php artisan config:cache` | berhasil (lalu `config:clear` di lokal) |
| `php artisan schedule:list` | `queue:work --stop-when-empty` tiap menit, `prospect-drafts` 09.00 — tidak berubah |
| Migration | tidak ada yang baru |
| Build frontend | tidak ada perubahan tampilan di tahap ini |
| Opt-out, approval pesan keluar, kebijakan harga | tercakup tes (BD 2.0 + 2.1) |
| Evaluasi model sungguhan | **terblokir** (tidak ada kunci AI lokal) |

Catatan queue: coalescing memakai `dispatch()->delay()`. Di server ini queue diproses `queue:work` dari scheduler tiap menit, jadi balasan bisa tertunda hingga ±1 menit, bukan 8 detik. Karena itu **default mati**. Nyalakan hanya setelah ada worker yang berjalan terus, atau terima tundaannya.

Tes yang ditambahkan untuk ini: race out-of-order, duplikat job, lock per nomor (tidak tumpang tindih; bila waktu tunggu habis, job queue di-release, tidak hilang), gateway gagal (tidak ada pesan yang dicatat terkirim), pesan stop/minta orang tidak ditunda.

Status: **IMPLEMENTED WITH LIMITATIONS** — siap untuk pilot terbatas ke nomor uji dan beberapa prospek yang diverifikasi founder, dengan setiap pesan pembuka tetap disetujui manual. Belum siap untuk outreach lebih luas sebelum: (1) evaluasi model sungguhan dijalankan dan dibaca, (2) jam layanan support ditetapkan, (3) kebijakan izin kontak WhatsApp dipastikan untuk tiap sumber nomor.

Rollback: set flag `bd21.*` ke false di `.env` + `config:cache`, atau revert commit.
