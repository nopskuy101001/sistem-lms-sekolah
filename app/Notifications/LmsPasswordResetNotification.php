<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LmsPasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Atur Ulang Password - RuangKelas SMK')
            ->greeting('Halo, ' . $notifiable->name . '!')
            ->line('Kami menerima permintaan untuk mengatur ulang password akun LMS sekolah Anda.')
            ->action('Buat Password Baru', $url)
            ->line('Tautan ini berlaku selama 30 menit dan hanya dapat digunakan sekali.')
            ->line('Jika tidak mengajukan permintaan ini, abaikan email. Password Anda tidak berubah.');
    }
}
