<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SchoolApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Tenant $tenant,
        private string $password,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $domain    = config('app.domain');
        $loginUrl  = 'https://' . $this->tenant->slug . '.admin.' . $domain . '/login';

        return (new MailMessage)
            ->subject('Welcome to SchoolMS Ghana — Your School is Approved!')
            ->greeting('Welcome, ' . ($this->tenant->contact_name ?? $this->tenant->name) . '!')
            ->line('Your school **' . $this->tenant->name . '** has been approved and is ready to use SchoolMS Ghana.')
            ->line('**Your login credentials:**')
            ->line('Email: ' . $notifiable->email)
            ->line('Password: ' . $this->password)
            ->line('**Your admin portal:**')
            ->action('Login to Your School Portal', $loginUrl)
            ->line('Please change your password immediately after your first login.')
            ->line('Your account starts with a **free trial** subscription. You can explore all features before your subscription begins.')
            ->salutation('The SchoolMS Ghana Team');
    }
}
