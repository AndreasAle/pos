<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PhoneOtpService;
use Illuminate\Http\Request;

/**
 * Verifikasi pemilik baru lewat kode WhatsApp. Dipakai saat mendaftar dari
 * aplikasi Android; verifikasi email tetap tersedia sebagai cadangan.
 */
class PhoneVerificationController extends Controller
{
    public function __construct(private PhoneOtpService $otp) {}

    public function show(Request $request)
    {
        $user = $request->user();

        if ($user->isVerifiedOwner()) {
            return redirect()->route($this->destinationFor($user));
        }
        if (! $user->phone) {
            return redirect()->route('verification.notice', ['via' => 'email']);
        }

        // Kode pertama terkirim otomatis saat halaman dibuka.
        if (! $this->otp->hasActiveCode($user) && $this->otp->secondsUntilResend($user) === 0) {
            $sent = $this->otp->send($user);
            if (! $sent['ok']) {
                session()->now('otp_error', $sent['error']);
            }
        }

        return view('auth.verify-whatsapp', [
            'phone' => $user->phone,
            'wait'  => $this->otp->secondsUntilResend($user),
        ]);
    }

    public function resend(Request $request)
    {
        $result = $this->otp->send($request->user());

        return $result['ok']
            ? back()->with('success', 'Kode baru sudah dikirim ke WhatsApp.')
            : back()->with('otp_error', $result['error']);
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string|max:12']);
        $user   = $request->user();
        $result = $this->otp->verify($user, $request->input('code'));

        if (! $result['ok']) {
            return back()->with('otp_error', $result['error']);
        }

        return redirect()->route($this->destinationFor($user->fresh()))
            ->with('success', 'Nomor WhatsApp terverifikasi.');
    }

    private function destinationFor($user): string
    {
        return $user->requires_business_setup && ! $user->business_id
            ? 'onboarding.business'
            : 'dashboard';
    }
}
