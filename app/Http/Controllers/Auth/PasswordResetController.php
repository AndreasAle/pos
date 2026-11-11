<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function showForgot()
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        try {
            Password::sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            report($e);
        }

        // Jawaban sama untuk email terdaftar maupun tidak, supaya orang luar
        // tidak bisa menebak email mana yang punya akun.
        return back()->with('success', 'Jika email itu terdaftar, tautan untuk mengatur password baru sudah kami kirim. Cek kotak masuk dan folder spam.');
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                // Model User meng-hash lewat cast 'hashed'.
                $user->forceFill([
                    'password'       => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // Yang bisa membuka email ini terbukti memilikinya.
                if ($user->isOwner() && ! $user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Password berhasil diubah. Silakan masuk dengan password baru.');
        }

        return back()->withInput($request->only('email'))
            ->withErrors(['email' => 'Tautan tidak valid atau sudah kedaluwarsa. Minta tautan baru.']);
    }
}
