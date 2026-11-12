<?php

namespace Database\Seeders;

use App\Models\PlatformAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Membuat akun tim Yoka (super_admin) untuk /platform/login.
 *
 * Tidak ada email atau password yang ditulis di repo (repo ini publik). Semuanya dari environment:
 *
 *   PLATFORM_ADMIN_EMAIL=nama@domain.com php artisan db:seed --class=PlatformAdminSeeder
 *
 * Opsional:
 *   PLATFORM_ADMIN_NAME      nama tampilan (default "Tim Yoka")
 *   PLATFORM_ADMIN_ROLE      super_admin | support | finance (default super_admin)
 *   PLATFORM_ADMIN_PASSWORD  min. 12 karakter; kalau kosong dibuat acak dan DITAMPILKAN SEKALI di terminal
 *   PLATFORM_ADMIN_RESET=1   timpa password akun yang sudah ada (tanpa ini akun yang ada tidak diubah)
 *
 * Kode 2FA dikirim ke email ini, jadi pakai email yang benar-benar bisa kamu buka.
 */
class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower(trim((string) env('PLATFORM_ADMIN_EMAIL')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->command?->error('Isi PLATFORM_ADMIN_EMAIL dengan email yang valid. Contoh: PLATFORM_ADMIN_EMAIL=nama@domain.com php artisan db:seed --class=PlatformAdminSeeder');

            return;
        }
        $role = (string) env('PLATFORM_ADMIN_ROLE', 'super_admin');
        if (! in_array($role, ['super_admin', 'support', 'finance'], true)) {
            $this->command?->error('PLATFORM_ADMIN_ROLE harus super_admin, support, atau finance.');

            return;
        }

        $existing = PlatformAdmin::where('email', $email)->first();
        if ($existing && ! env('PLATFORM_ADMIN_RESET')) {
            $this->command?->warn("Akun {$email} sudah ada ({$existing->role}); tidak diubah. Pakai PLATFORM_ADMIN_RESET=1 untuk membuat password baru.");

            return;
        }

        $given = (string) env('PLATFORM_ADMIN_PASSWORD');
        if ($given !== '' && mb_strlen($given) < 12) {
            $this->command?->error('PLATFORM_ADMIN_PASSWORD minimal 12 karakter.');

            return;
        }
        $password = $given !== '' ? $given : Str::password(20, true, true, false);

        PlatformAdmin::updateOrCreate(['email' => $email], [
            'name' => (string) env('PLATFORM_ADMIN_NAME', 'Tim Yoka'),
            'password' => $password,
            'role' => $role,
            'is_active' => true,
        ]);

        $this->command?->info("Akun platform siap: {$email} ({$role})");
        if ($given === '') {
            $this->command?->line("Password sementara (hanya tampil sekali, segera simpan): {$password}");
        }
        $this->command?->line('Masuk di /platform/login. Kode 2FA dikirim ke email di atas.');
    }
}
