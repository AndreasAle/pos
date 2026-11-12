<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** Akun admin platform sendiri: ganti password dan kode pemulihan 2FA. */
class AccountController extends Controller
{
    public function show()
    {
        $admin = auth('platform')->user();

        return view('platform.account', ['admin' => $admin, 'remaining' => count((array) $admin->recovery_codes), 'newCodes' => session('new_recovery_codes')]);
    }

    public function password(Request $request): RedirectResponse
    {
        $admin = auth('platform')->user();
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(12)->letters()->numbers()],
        ], ['current_password.required' => 'Password saat ini wajib diisi.', 'password.min' => 'Password baru minimal 12 karakter.', 'password.confirmed' => 'Konfirmasi password tidak sama.']);

        if (! Hash::check($request->string('current_password')->toString(), $admin->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini salah.']);
        }
        $admin->forceFill(['password' => $request->string('password')->toString(), 'remember_token' => null])->save();
        PlatformActivityLog::record('account.password_changed', 'Mengganti password akun platform.');

        return back()->with('success', 'Password berhasil diganti.');
    }

    /** Buat ulang 8 kode pemulihan sekali pakai; kode lama otomatis tidak berlaku. Ditampilkan sekali saja. */
    public function recovery(Request $request): RedirectResponse
    {
        $admin = auth('platform')->user();
        $request->validate(['current_password' => ['required', 'string']], ['current_password.required' => 'Masukkan password untuk membuat kode pemulihan.']);
        if (! Hash::check($request->string('current_password')->toString(), $admin->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini salah.']);
        }

        $codes = $admin->generateRecoveryCodes();
        PlatformActivityLog::record('account.recovery_codes_created', 'Membuat ulang kode pemulihan 2FA.');

        return back()->with('new_recovery_codes', $codes)->with('success', 'Kode pemulihan baru dibuat. Simpan sekarang; tidak akan ditampilkan lagi.');
    }
}
