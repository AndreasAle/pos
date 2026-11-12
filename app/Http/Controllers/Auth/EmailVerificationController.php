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
            return redirect()->route($this->destinationFor($request->user()));
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

        $message = $user->requires_business_setup && ! $user->business_id
            ? 'Email berhasil dikonfirmasi. Selanjutnya, lengkapi informasi bisnis Anda.'
            : 'Email berhasil dikonfirmasi. Selamat datang di Yoka POS!';

        return Auth::check()
            ? redirect()->route($this->destinationFor(Auth::user()))->with('success', $message)
            : redirect()->route('login')->with('success', $message.' Silakan masuk.');
    }

    public function resend(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route($this->destinationFor($user));
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['email' => 'Email belum bisa terkirim saat ini. Coba lagi beberapa menit lagi.']);
        }

        return back()->with('success', 'Tautan konfirmasi baru sudah dikirim ke '.$user->email.'.');
    }

    private function destinationFor(User $user): string
    {
        return $user->requires_business_setup && ! $user->business_id
            ? 'onboarding.business'
            : 'dashboard';
    }
}
