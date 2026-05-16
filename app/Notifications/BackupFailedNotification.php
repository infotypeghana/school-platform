<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BackupFailedNotification extends Notification
{
    use Queueable;

    public function __construct(private string $errorMessage) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name');

        return (new MailMessage)
            ->subject("[{$appName}] ⚠️ Database Backup Failed")
            ->error()
            ->greeting('Database Backup Alert')
            ->line('The scheduled database backup failed. Immediate attention required.')
            ->line('**Error:** ' . $this->errorMessage)
            ->line('**Time:** ' . now()->toDateTimeString() . ' (' . config('app.timezone') . ')')
            ->line('**Environment:** ' . config('app.env'))
            ->action('Open Server', config('app.url'))
            ->line('Please investigate immediately. A missing backup means no recovery option if data loss occurs.');
    }
}
