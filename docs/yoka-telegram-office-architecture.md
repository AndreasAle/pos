# Yoka Telegram Virtual Office

Telegram Office adalah adapter komunikasi untuk AI Workforce yang sudah ada. Ia tidak memiliki engine AI, memory, decision ledger, atau task store sendiri.

## Aliran data

`Telegram webhook → validasi secret → deduplikasi event → queue → otorisasi user/group → router → PlatformAiOperations/PlatformAiDiscussions → outbound bot yang sesuai`.

Tujuh koneksi bot dipetakan satu-ke-satu ke `platform_ai_agents`. Token hanya berasal dari environment. URL webhook memakai UUID publik non-rahasia; autentikasi sesungguhnya memakai `X-Telegram-Bot-Api-Secret-Token`.

Update disatukan melalui fingerprint `chat_id + message_id`, sehingga pesan grup yang diterima beberapa bot hanya diproses sekali. Pesan yang berasal dari bot diabaikan untuk mencegah loop.

## Mode

- `mention_only`: hanya `/ask`, mention bot, reply terkontrol, dan command.
- `smart_discussion`: router deterministik memilih maksimal tiga agen.
- `/meeting`: maksimal empat agen, satu ronde, memakai `PlatformAiDiscussions` dan menyimpan kontribusi nyata.

Memory, keputusan, task, dan data tool tetap shared. Temporary state memakai `channel + context_key`, sehingga histori dashboard, grup, dan topic tidak tercampur.

Task yang dibuat model tetap masuk `platform_ai_tasks` dan approval sensitif tetap di dashboard. Telegram tidak menyediakan shortcut approval.

## Operasional

Queue memproses webhook dengan tiga percobaan dan bounded backoff. Scheduler mengirim briefing idempoten dan alert pending dengan cooldown, quiet hours, serta batas harian. Delivery hanya berstatus berhasil bila Telegram mengembalikan `ok=true`.

## Kualitas percakapan & kebijakan aksi (2.1)

Lihat `yoka-telegram-conversation-quality.md` dan `yoka-ai-workforce-action-policy.md`: klasifikasi niat, validasi bukti tindakan, gerbang kontribusi, ledger notifikasi (`platform_ai_telegram_notifications`), hasil tugas kembali ke grup asal, dan memori kerja per agen.
