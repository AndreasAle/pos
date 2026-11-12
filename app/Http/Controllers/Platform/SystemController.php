<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformActivityLog;
use App\Services\PlatformHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class SystemController extends Controller
{
    public function index(PlatformHealth $health)
    {
        abort_unless(auth('platform')->user()->isSuperAdmin(), 403);
        $report = $health->run();

        return view('platform.system', ['report' => $report, 'summary' => $health->summary($report), 'checkedAt' => now()]);
    }

    /** Kirim email tes ke alamat admin yang sedang login (bukan alamat bebas) untuk membuktikan SMTP benar-benar mengirim. */
    public function testMail(): RedirectResponse
    {
        $admin = auth('platform')->user();
        abort_unless($admin->isSuperAdmin(), 403);

        try {
            Mail::raw('Tes email dari Yoka Platform. Kalau email ini sampai di kotak masukmu, pengiriman email (kode 2FA, lupa password) berfungsi. Dikirim '.now()->format('d M Y H:i').'.', fn ($m) => $m->to($admin->email)->subject('Tes email Yoka Platform'));
        } catch (\Throwable $e) {
            report($e);
            PlatformActivityLog::record('system.mail_test_failed', 'Tes email gagal: '.class_basename($e).'.');

            return back()->withErrors(['mail' => 'Email gagal dikirim ('.class_basename($e).'). Periksa MAIL_* di server.']);
        }
        PlatformActivityLog::record('system.mail_test', 'Mengirim email tes ke akun sendiri.');

        return back()->with('success', 'Email tes diterima server email dan dikirim ke '.$admin->email.'. Cek kotak masuk dan folder spam.');
    }
}
