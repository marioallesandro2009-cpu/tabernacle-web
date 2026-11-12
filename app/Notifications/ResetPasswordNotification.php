<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/admin/reset-password/'.$this->token.'?email='.urlencode($notifiable->email));
        $minutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Atur ulang password admin '.config('app.name'))
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Kami menerima permintaan untuk mengatur ulang password akun admin Anda.')
            ->action('Atur ulang password', $url)
            ->line('Tautan ini berlaku selama '.$minutes.' menit. Jika Anda tidak merasa meminta ini, abaikan email ini.');
    }
}
