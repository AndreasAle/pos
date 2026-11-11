<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlatformTwoFactorCode extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kode keamanan Yoka Platform')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Gunakan kode berikut untuk menyelesaikan login ke dashboard tim Yoka:')
            ->line('**'.$this->code.'**')
            ->line('Kode berlaku selama 10 menit dan hanya dapat digunakan satu kali.')
            ->line('Jika Anda tidak mencoba login, segera ganti password akun platform Anda.');
    }
}
