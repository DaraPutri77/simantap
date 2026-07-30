<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        #[\SensitiveParameter] public readonly string $token,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $expires = (int) config(
            'auth.passwords.users.expire',
            60,
        );

        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Reset kata sandi SIMANTAP')
            ->greeting("Halo, {$notifiable->name}")
            ->line(
                'Kami menerima permintaan reset kata sandi '
                .'akun SIMANTAP Anda.',
            )
            ->line(
                "Tautan berikut berlaku selama {$expires} menit "
                .'dan hanya dapat digunakan sekali.',
            )
            ->action('Reset Kata Sandi', $url)
            ->line(
                'Abaikan pesan ini jika Anda tidak meminta '
                .'reset kata sandi.',
            );
    }
}
