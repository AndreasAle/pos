<?php

namespace App\Services;

use App\Models\PlatformAdmin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Pemeriksaan kesehatan seluruh sistem Yoka dalam satu tempat: dipakai halaman Platform > Sistem
 * dan perintah `php artisan yoka:health`. Hanya melaporkan STATUS; nilai rahasia (kunci, password,
 * token) tidak pernah ikut ke hasil.
 *
 * Status: ok | warn | fail | info
 */
class PlatformHealth
{
    public const HEARTBEAT_KEY = 'scheduler:heartbeat';

    /** @return array<string, array{title:string, checks: array<int, array{name:string, status:string, detail:string, hint?:string}>}> */
    public function run(): array
    {
        return [
            'app' => ['title' => 'Aplikasi', 'checks' => $this->app()],
            'data' => ['title' => 'Database & penyimpanan', 'checks' => array_merge($this->database(), $this->storage())],
            'jobs' => ['title' => 'Penjadwalan & antrean', 'checks' => $this->jobs()],
            'integrations' => ['title' => 'Integrasi', 'checks' => array_merge($this->mail(), $this->telegram(), $this->ai(), $this->google(), $this->midtrans())],
            'security' => ['title' => 'Keamanan', 'checks' => $this->security()],
            'errors' => ['title' => 'Galat 24 jam terakhir', 'checks' => $this->errors()],
        ];
    }

    /** @return array{ok:int, warn:int, fail:int} */
    public function summary(array $report): array
    {
        $count = ['ok' => 0, 'warn' => 0, 'fail' => 0];
        foreach ($report as $group) {
            foreach ($group['checks'] as $c) {
                if (isset($count[$c['status']])) {
                    $count[$c['status']]++;
                }
            }
        }

        return $count;
    }

    private function c(string $name, string $status, string $detail, ?string $hint = null): array
    {
        return array_filter(['name' => $name, 'status' => $status, 'detail' => $detail, 'hint' => $hint], fn ($v) => $v !== null);
    }

    private function app(): array
    {
        $commit = null;
        $head = base_path('.git/HEAD');
        if (is_file($head)) {
            $ref = trim((string) file_get_contents($head));
            $file = str_starts_with($ref, 'ref: ') ? base_path('.git/'.substr($ref, 5)) : null;
            $commit = $file && is_file($file) ? substr(trim((string) file_get_contents($file)), 0, 7) : substr($ref, 0, 7);
        }

        return [
            $this->c('Versi kode', $commit ? 'info' : 'warn', $commit ? "Commit {$commit}" : 'Tidak terbaca (.git tidak ada)'),
            $this->c('Lingkungan', app()->isProduction() ? 'ok' : 'warn', 'APP_ENV='.config('app.env').' · PHP '.PHP_VERSION.' · Laravel '.app()->version(), app()->isProduction() ? null : 'Server produksi harus APP_ENV=production.'),
            $this->c('Mode debug', config('app.debug') ? 'fail' : 'ok', config('app.debug') ? 'APP_DEBUG menyala' : 'Mati', config('app.debug') ? 'Matikan APP_DEBUG=false; jejak galat bisa membocorkan rahasia.' : null),
            $this->c('Zona waktu', 'info', config('app.timezone').' · sekarang '.now()->format('d M Y H:i')),
            $this->c('URL aplikasi', str_starts_with((string) config('app.url'), 'https://') ? 'ok' : 'warn', (string) config('app.url'), str_starts_with((string) config('app.url'), 'https://') ? null : 'APP_URL sebaiknya https.'),
        ];
    }

    private function database(): array
    {
        $out = [];
        try {
            $t = microtime(true);
            DB::select('select 1');
            $ms = round((microtime(true) - $t) * 1000);
            $out[] = $this->c('Koneksi database', $ms > 400 ? 'warn' : 'ok', DB::connection()->getDatabaseName()." · {$ms} ms");

            $files = collect(File::files(database_path('migrations')))->map(fn ($f) => $f->getFilenameWithoutExtension());
            $ran = Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration') : collect();
            $pending = $files->diff($ran)->count();
            $out[] = $this->c('Migrasi', $pending ? 'fail' : 'ok', $pending ? "{$pending} migrasi belum dijalankan" : 'Semua migrasi sudah dijalankan', $pending ? 'Jalankan php artisan migrate --force (setelah backup).' : null);

            $out[] = $this->c('Toko aktif', 'info', DB::table('businesses')->where('is_active', true)->count().' dari '.DB::table('businesses')->count().' toko · '.DB::table('users')->count().' pengguna');
        } catch (\Throwable $e) {
            $out[] = $this->c('Koneksi database', 'fail', 'Gagal: '.class_basename($e));
        }

        return $out;
    }

    private function storage(): array
    {
        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());
        $pct = $free && $total ? round($free / $total * 100) : null;
        $writable = collect(['storage/logs', 'storage/framework/cache', 'storage/app', 'bootstrap/cache'])->reject(fn ($p) => is_writable(base_path($p)))->values();

        return [
            $this->c('Folder bisa ditulis', $writable->isEmpty() ? 'ok' : 'fail', $writable->isEmpty() ? 'storage dan bootstrap/cache dapat ditulis' : 'Tidak bisa ditulis: '.$writable->implode(', ')),
            $this->c('Ruang disk', $pct === null ? 'info' : ($pct < 10 ? 'fail' : ($pct < 20 ? 'warn' : 'ok')), $pct === null ? 'Tidak terbaca' : "{$pct}% kosong (".round($free / 1073741824, 1).' GB)'),
            $this->c('Ekstensi gambar (GD)', function_exists('imagecreatefromstring') ? 'ok' : 'warn', function_exists('imagecreatefromstring') ? 'Aktif' : 'Tidak aktif', function_exists('imagecreatefromstring') ? null : 'Unggah foto profil butuh GD.'),
        ];
    }

    private function jobs(): array
    {
        $out = [];
        $beat = (int) Cache::get(self::HEARTBEAT_KEY, 0);
        $age = $beat ? now()->timestamp - $beat : null;
        $out[] = $this->c('Cron (schedule:run)', $age === null ? 'fail' : ($age > 180 ? 'fail' : 'ok'),
            $age === null ? 'Belum pernah berdetak' : "Terakhir jalan {$age} detik lalu",
            $age === null || $age > 180 ? 'Pasang cron tiap menit: /usr/bin/php '.base_path('artisan').' schedule:run (hPanel > Cron Jobs).' : null);

        $driver = (string) config('queue.default');
        try {
            $pending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
            $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
            $out[] = $this->c('Antrean', $driver === 'sync' ? 'ok' : ($pending > 20 ? 'warn' : 'ok'), "Driver {$driver} · {$pending} menunggu", $driver === 'database' && $pending > 0 ? 'Tidak ada worker yang berjalan; pekerjaan ini tidak akan diproses.' : null);
            $out[] = $this->c('Pekerjaan gagal', $failed ? 'warn' : 'ok', $failed ? "{$failed} pekerjaan gagal" : 'Tidak ada');
        } catch (\Throwable $e) {
            $out[] = $this->c('Antrean', 'warn', 'Tidak terbaca');
        }

        return $out;
    }

    private function mail(): array
    {
        $m = config('mail.mailers.'.config('mail.default'), []);
        $ok = config('mail.default') !== 'log' && filled($m['host'] ?? null) && filled($m['username'] ?? null) && filled($m['password'] ?? null);

        return [$this->c('Email (SMTP)', $ok ? 'ok' : 'fail',
            'Mailer '.config('mail.default').' · '.($m['host'] ?? '-').':'.($m['port'] ?? '-').' · pengirim '.config('mail.from.address').' · akun '.(filled($m['username'] ?? null) ? 'terisi' : 'KOSONG').', password '.(filled($m['password'] ?? null) ? 'terisi' : 'KOSONG'),
            $ok ? 'Pakai tombol "Kirim email tes" untuk memastikan email benar-benar sampai.' : 'Isi MAIL_HOST, MAIL_USERNAME, dan MAIL_PASSWORD. Email lupa password dan kode 2FA bergantung padanya.')];
    }

    private function telegram(): array
    {
        $token = config('services.telegram.token');
        if (! filled($token)) {
            return [$this->c('Telegram bot', 'warn', 'TELEGRAM_BOT_TOKEN belum diisi', 'Notifikasi order dan asisten Telegram tidak aktif.')];
        }
        $out = [];
        try {
            $info = Http::timeout(6)->get("https://api.telegram.org/bot{$token}/getWebhookInfo")->json('result', []);
            $url = (string) ($info['url'] ?? '');
            $expected = rtrim((string) config('app.url'), '/').'/telegram/webhook';
            $err = $info['last_error_message'] ?? null;
            $out[] = $this->c('Webhook Telegram', $url === '' ? 'fail' : ($url !== $expected ? 'warn' : ($err ? 'warn' : 'ok')),
                $url === '' ? 'Webhook belum dipasang' : ($url !== $expected ? 'Mengarah ke domain lain: '.parse_url($url, PHP_URL_HOST) : 'Terpasang'.($err ? ' · galat terakhir: '.$err : ' · '.(int) ($info['pending_update_count'] ?? 0).' update menunggu')),
                $url === '' || $url !== $expected ? 'Jalankan php artisan telegram:webhook.' : null);
        } catch (\Throwable $e) {
            $out[] = $this->c('Webhook Telegram', 'warn', 'Tidak bisa dihubungi: '.class_basename($e));
        }
        $linked = DB::table('businesses')->whereNotNull('settings')->get(['settings'])->filter(fn ($b) => ! empty(json_decode($b->settings, true)['telegram']['chat_id'] ?? null))->count();
        $out[] = $this->c('Toko terhubung Telegram', 'info', "{$linked} toko");

        return $out;
    }

    private function ai(): array
    {
        $key = filled(config('services.yoka_ai.key'));
        $out = [$this->c('Yoka AI (OpenAI)', $key ? 'ok' : 'fail', $key ? 'Kunci terisi · model '.config('services.yoka_ai.model').(config('services.yoka_ai.deep_model') ? ' / analisis '.config('services.yoka_ai.deep_model') : '') : 'YOKA_AI_API_KEY kosong', $key ? null : 'Asisten berjalan tanpa AI (mesin kata kunci).')];
        try {
            $day = DB::table('assistant_messages')->where('role', 'assistant')->where('created_at', '>=', now()->subDay())->get(['token_usage']);
            $tokens = $day->sum(fn ($m) => (int) (json_decode((string) $m->token_usage, true)['total_tokens'] ?? 0));
            $out[] = $this->c('Pemakaian AI 24 jam', 'info', $day->count().' jawaban · '.number_format($tokens, 0, ',', '.').' token', 'Anggaran: '.config('services.yoka_ai.daily_request_budget', 250).' permintaan per toko per hari.');
        } catch (\Throwable $e) {
        }

        return $out;
    }

    private function google(): array
    {
        $set = filled(config('services.google.client_id')) && filled(config('services.google.client_secret')) && filled(config('services.google.redirect'));
        $expected = rtrim((string) config('app.url'), '/').'/auth/google/callback';
        $match = config('services.google.redirect') === $expected;

        return [$this->c('Login Google', $set ? ($match ? 'ok' : 'warn') : 'fail',
            $set ? 'Terisi · redirect '.config('services.google.redirect') : 'GOOGLE_CLIENT_ID/SECRET belum diisi',
            $set ? ($match ? 'Pastikan URL ini terdaftar di Google Cloud Console (Authorized redirect URIs).' : "Redirect tidak sama dengan {$expected}.") : 'Tombol Masuk dengan Google akan kembali ke halaman login.')];
    }

    private function midtrans(): array
    {
        $set = filled(config('midtrans.server_key')) && filled(config('midtrans.client_key'));

        return [$this->c('Midtrans (QRIS)', $set ? 'ok' : 'warn', $set ? 'Kunci terisi · mode '.(config('midtrans.is_production') ? 'PRODUKSI' : 'sandbox') : 'Kunci belum diisi', $set && ! config('midtrans.is_production') ? 'Masih mode sandbox; transaksi nyata belum diproses.' : null)];
    }

    private function security(): array
    {
        $out = [];
        $admins = PlatformAdmin::query()->get(['role', 'is_active', 'last_login_at']);
        $supers = $admins->where('role', 'super_admin')->where('is_active', true)->count();
        $out[] = $this->c('Akun tim Yoka', $supers ? 'ok' : 'fail', $admins->count().' akun · '.$supers.' super admin aktif', $supers ? null : 'Buat akun: PLATFORM_ADMIN_EMAIL=... php artisan db:seed --class=PlatformAdminSeeder');
        $noRecovery = PlatformAdmin::query()->where('is_active', true)->get(['recovery_codes'])->filter(fn ($a) => empty($a->recovery_codes))->count();
        $out[] = $this->c('Kode pemulihan 2FA', $noRecovery ? 'warn' : 'ok', $noRecovery ? "{$noRecovery} akun aktif belum punya kode pemulihan" : 'Semua akun aktif punya cadangan', $noRecovery ? 'Buat di Platform > Akun Saya agar tidak terkunci bila email 2FA tidak sampai.' : null);
        $out[] = $this->c('Cookie sesi aman', config('session.secure') || str_starts_with((string) config('app.url'), 'https://') ? 'ok' : 'warn', 'secure='.var_export((bool) config('session.secure'), true).' · driver '.config('session.driver'));
        $logins = DB::table('platform_activity_logs')->where('action', 'login.failed')->where('created_at', '>=', now()->subDay())->count();
        $out[] = $this->c('Gagal login platform 24 jam', $logins >= 10 ? 'warn' : 'ok', "{$logins} percobaan gagal");

        return $out;
    }

    private function errors(): array
    {
        $log = storage_path('logs/laravel.log');
        if (! is_file($log)) {
            return [$this->c('Log aplikasi', 'ok', 'Belum ada log')];
        }
        $size = filesize($log);
        $tail = (string) @file_get_contents($log, false, null, max(0, $size - 400000));
        preg_match_all('/^\[(\d{4}-\d{2}-\d{2} [\d:]{8})\] \w+\.ERROR: (.{0,160})/m', $tail, $m, PREG_SET_ORDER);
        $recent = collect($m)->filter(fn ($x) => strtotime($x[1]) >= now()->subDay()->timestamp);
        $top = $recent->groupBy(fn ($x) => preg_replace('/\d+/', '#', mb_substr($x[2], 0, 90)))->map->count()->sortDesc()->take(3);
        $out = [$this->c('Galat (ERROR)', $recent->count() > 20 ? 'fail' : ($recent->count() ? 'warn' : 'ok'), $recent->count().' galat · log '.round($size / 1048576, 1).' MB')];
        foreach ($top as $msg => $n) {
            $out[] = $this->c('Paling sering', 'info', "{$n}× ".trim($msg));
        }

        return $out;
    }
}
