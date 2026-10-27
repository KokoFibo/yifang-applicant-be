<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyApplicantEmail extends Notification
{
    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Konfirmasi email pendaftaran - ' . config('app.name'))
            ->greeting('Halo,')
            ->line('Terima kasih sudah mendaftar di portal calon karyawan ' . config('app.name') . '.')
            ->line('Klik tombol di bawah untuk mengonfirmasi email Anda. Tautan berlaku 24 jam.')
            ->action('Konfirmasi email', $notifiable->frontendVerificationUrl())
            ->line('Jika Anda tidak merasa mendaftar, abaikan email ini.')
            ->salutation('Salam, ' . config('mail.from.name'));
    }
}
