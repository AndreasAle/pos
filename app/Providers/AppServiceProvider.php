<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Email konfirmasi dan reset password memakai tampilan Yoka POS.
        VerifyEmail::toMailUsing(function ($notifiable, string $url) {
            $subject = 'Konfirmasi email akun Yoka POS';

            return (new MailMessage)->subject($subject)->view('emails.auth', [
                'subject' => $subject,
                'heading' => 'Konfirmasi email Anda',
                'name'    => $notifiable->name,
                'intro'   => 'Terima kasih sudah mendaftar di Yoka POS. Tekan tombol di bawah untuk mengonfirmasi email dan mulai memakai akun Anda.',
                'button'  => 'Konfirmasi Email',
                'url'     => $url,
                'note'    => 'Tautan berlaku 60 menit. Kalau Anda tidak merasa mendaftar, abaikan saja email ini.',
            ]);
        });

        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $subject = 'Atur ulang password Yoka POS';
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            return (new MailMessage)->subject($subject)->view('emails.auth', [
                'subject' => $subject,
                'heading' => 'Atur ulang password',
                'name'    => $notifiable->name,
                'intro'   => 'Kami menerima permintaan untuk mengatur ulang password akun Yoka POS Anda. Tekan tombol di bawah untuk membuat password baru.',
                'button'  => 'Buat Password Baru',
                'url'     => $url,
                'note'    => 'Tautan berlaku 60 menit. Kalau Anda tidak memintanya, abaikan email ini; password Anda tidak berubah.',
            ]);
        });
    }
}
