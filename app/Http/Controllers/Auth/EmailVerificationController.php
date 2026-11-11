<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailVerificationController extends Controller
{
    /** Halaman "cek email Anda" untuk pemilik yang belum konfirmasi. */
    public function notice(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return view('auth.verify-email', ['email' => $request->user()->email]);
    }

    /** Tautan dari email. Boleh dibuka tanpa login (mis. dari HP). */
    public function verify(Request $request, int $id, string $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $message = 'Email berhasil dikonfirmasi. Selamat datang di Yoka POS!';

        return Auth::check()
            ? redirect()->route('dashboard')->with('success', $message)
            : redirect()->route('login')->with('success', $message . ' Silakan masuk.');
    }

    public function resend(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['email' => 'Email belum bisa terkirim saat ini. Coba lagi beberapa menit lagi.']);
        }

        return back()->with('success', 'Tautan konfirmasi baru sudah dikirim ke ' . $user->email . '.');
    }
}
