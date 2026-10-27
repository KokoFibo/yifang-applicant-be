<?php

namespace App\Notifications;

use App\Support\CompanyContext;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetApplicantPassword extends Notification
{
    public const EXPIRE_MINUTES = 60;

    public function __construct(public string $token)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = app(CompanyContext::class)->baseUrl() . '/reset-password?' . http_build_query([
            'token' => $this->token,
            'email' => $notifiable->email,
        ]);
        $minutes = self::EXPIRE_MINUTES;

        return (new MailMessage)
            ->subject('Atur ulang password - ' . config('app.name'))
            ->greeting('Halo,')
            ->line('Kami menerima permintaan untuk mengatur ulang password akun Anda.')
            ->action('Atur ulang password', $url)
            ->line("Tautan ini berlaku {$minutes} menit.")
            ->line('Jika Anda tidak memintanya, abaikan email ini. Password Anda tidak berubah.')
            ->salutation('Salam, ' . config('mail.from.name'));
    }
}
