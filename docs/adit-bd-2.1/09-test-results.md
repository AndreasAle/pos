# 09 — Hasil tes (2026-11-22, MariaDB lokal)

| Perintah | Hasil |
|---|---|
| `phpunit --filter "AditConversationQuality\|AditBusinessDevelopment\|ProspectDesk"` | lulus setelah perbaikan |
| `phpunit` (penuh) | **OK (825 tests, 8688 assertions)** |
| `npm run build` | berhasil |

`tests/Feature/PlatformAi/AditConversationQualityTest.php` (18 tes) mencakup 20 skenario: s01 nama bisnis, s02 kertas, s03 trauma servis, s04–05 kasir ramai & margin, s06 harga, s07 printer, s08/s12 diskon & approval palsu, s09 penutupan, s10 pesan beruntun → satu pesan, s11 opt-out, s13 fitur tidak ada, s14 minta trial, s15 pelanggan aktif, s16 minta admin, s17 bundel printer, s18 SLA, s19 data kurang, s20 percakapan panjang, plus uji flag.

Satu tes lama diubah karena perilakunya memang sengaja diganti: `ProspectDeskTest` kini memastikan pembuka **tidak** memuat link trial.

Bug yang ditemukan tes saat pengembangan: "troma" dan keluhan servis tidak dikenali sebagai pengalaman buruk; "pemiliknya" tidak terbaca sebagai pemilik; "margin" tidak tercatat sebagai masalah; "Kalau Rp50 ribu bisa?" tidak dikenali sebagai permintaan harga.

Belum diuji: kualitas kalimat dengan model sungguhan (balasan model di tes dipalsukan), gateway WhatsApp sungguhan.
