<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route($this->destinationFor(Auth::user()));
        }

        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'business_id' => null,
            'outlet_id' => null,
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => 'owner',
            'is_active' => true,
            'requires_business_setup' => true,
            'phone' => $request->phone,
        ]);

        Auth::login($user);

        // Kirim email konfirmasi. Kegagalan SMTP tidak boleh membatalkan pendaftaran:
        // pengguna bisa menekan "Kirim ulang" di halaman berikutnya.
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('verification.notice');
    }

    private function destinationFor(User $user): string
    {
        if (! $user->hasVerifiedEmail()) {
            return 'verification.notice';
        }

        return $user->requires_business_setup && ! $user->business_id
            ? 'onboarding.business'
            : 'dashboard';
    }
}
