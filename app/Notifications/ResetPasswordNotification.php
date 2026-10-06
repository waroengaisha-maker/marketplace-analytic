<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = $notifiable->getEmailForPasswordReset();
        $url = url('/reset-password/'.$this->token.'?email='.urlencode($email));

        return (new MailMessage)
            ->subject('Reset Password — Marketplace Analytics')
            ->view('emails.password-reset', [
                'name' => $notifiable->name,
                'url' => $url,
            ]);
    }
}
