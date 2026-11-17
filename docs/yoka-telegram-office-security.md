# Keamanan Telegram Virtual Office

- Hanya Platform Super Admin yang dapat membuat kode link dan mengubah konfigurasi.
- Identitas memakai numeric Telegram user ID; username hanya metadata.
- Kode link di-hash, berlaku 10 menit, dan sekali pakai.
- Group ID harus terdaftar dan terikat ke owner yang sama.
- Setiap webhook wajib secret header, rate limit, validasi payload, unique update, dan global event fingerprint.
- Token bot tidak disimpan di database, response, UI, log, atau URL.
- Payload tersimpan dibatasi ke field yang diperlukan; error tidak menyimpan token/API response sensitif.
- Bot-generated messages tidak memicu workflow. Meeting dibatasi empat peserta dan satu ronde.
- Telegram tidak boleh menyetujui tindakan sensitif. Iklan, pengeluaran, refund, harga, dan pesan eksternal tetap melalui dashboard.
- Shared memory hanya dibaca sesuai scope owner/shared/agent. Temporary conversation state diisolasi per channel dan chat/topic.
- Outbound dianggap sukses hanya setelah konfirmasi Telegram. Retry dibatasi.
- Revoke menonaktifkan identitas dan grup tanpa menghapus audit trail.

Sebelum rollout: backup database, rotasi secret unik, set webhook HTTPS, verifikasi queue/scheduler, aktifkan monitoring error/cost, uji revoke, dan pastikan situs klien tidak menjadi target deploy.
