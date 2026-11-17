# Setup Telegram Virtual Office

1. Buat tujuh bot di BotFather: Ramli, Yusuf, Yono Bakri, Luna, Nanda, Adit, dan Yosef.
2. Isi token, username, dan random webhook secret masing-masing pada environment server sesuai `.env.example`. Secret hanya boleh memakai karakter `A-Z`, `a-z`, `0-9`, `_`, dan `-`.
3. Jalankan migration di environment yang telah dibackup.
4. Pastikan cron menjalankan `php artisan schedule:run` setiap menit dan queue database tersedia.
5. Buka Platform → Tim AI → Telegram Office. Pastikan tujuh kartu bot aktif.
6. Daftarkan webhook masing-masing bot ke URL yang ditampilkan/dibentuk dari `route('platform.telegram.webhook', public_key)` dengan `secret_token` yang sama seperti environment. Pendaftaran live harus dilakukan manual oleh operator dan tidak dilakukan test suite.
7. Buat kode tautan di dashboard, lalu kirim `/link KODE` melalui DM ke salah satu bot.
8. Tambahkan tujuh bot ke private supergroup dan kirim `/connect` dari akun Telegram yang sudah tertaut.
9. Pilih Mention Only atau Smart Discussion dan atur briefing/quiet hours.

Untuk Smart Discussion berbasis pesan bebas, minimal bot intake (disarankan Ramli) harus menjadi admin grup atau Group Privacy Mode-nya dinonaktifkan. Mention Only dapat tetap memakai privacy mode default. Backend tetap mengabaikan pesan bot dan melakukan deduplikasi bila beberapa bot menerima pesan yang sama.

Perintah: `/ask ramli ...`, `/meeting ...`, `/tasks`, `/status`, `/team`, `/briefing`, `/help`.

## Pengujian

Gunakan database MySQL terisolasi. HTTP Telegram harus di-fake pada automated test. Jangan memakai token produksi di test. Live checklist: validasi getMe, webhook info, mention tiap bot, duplicate update, meeting, task link, delivery failure, revoke, dan bot removal.
