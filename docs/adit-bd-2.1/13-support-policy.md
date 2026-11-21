# 13 — Kebijakan layanan bantuan

Sumber: `config/yoka_support.php` (diisi lewat `.env` server):
| Kunci | .env | Default |
|---|---|---|
| human_hours | `YOKA_SUPPORT_HOURS` | null (belum ditetapkan) |
| response_target | `YOKA_SUPPORT_RESPONSE_TARGET` | null |
| installation_help | `YOKA_SUPPORT_INSTALLATION` | null |
| channels | — | WhatsApp resmi (Adit AI + tim manusia bila perlu), panduan yokapos.com/panduan |

`SupportPolicy::promptLine()` masuk ke konteks prospek: membedakan Adit (AI otomatis) dari tim manusia; bila jam/target/instalasi kosong, Adit wajib bilang belum ada jadwal resmi dan menawarkan meneruskan ke tim. `ResponseQuality` membuang klaim 24 jam/respon cepat/dijamin, kecuali kalimat yang menyebut jam resmi yang dikonfigurasi.

Belum ada perbedaan layanan per paket (`package_entitlements` kosong). Belum ada halaman admin untuk mengubahnya; pakai `.env` lalu `php artisan config:cache`.
