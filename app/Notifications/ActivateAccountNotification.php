<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ActivateAccountNotification extends Notification
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
            'simantap.security.activation_expire_minutes',
            60,
        );

        $url = route('activation.show', [
            'token' => $this->token,
        ]);

        return (new MailMessage)
            ->subject('Aktifkan akun SIMANTAP')
            ->greeting("Halo, {$notifiable->name}")
            ->line(
                'Akun SIMANTAP Anda telah dibuat oleh administrator.',
            )
            ->line(
                'Buat kata sandi pribadi melalui tombol berikut. '
                ."Tautan berlaku selama {$expires} menit dan "
                .'hanya dapat digunakan sekali.',
            )
            ->action('Aktifkan Akun', $url)
            ->line(
                'Abaikan pesan ini jika Anda tidak mengenali '
                .'permintaan tersebut.',
            );
    }
}
