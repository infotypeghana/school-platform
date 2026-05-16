<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewSchoolRegistrationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Tenant $tenant) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approveUrl = url('/superadmin/tenants/' . $this->tenant->id);

        return (new MailMessage)
            ->subject('New School Registration: ' . $this->tenant->name)
            ->greeting('New School Registration')
            ->line($this->tenant->contact_name . ' from **' . $this->tenant->name . '** has requested access to ' . config('app.name') . '.')
            ->line('**District:** ' . ($this->tenant->district ?? '—'))
            ->line('**School type:** ' . ucfirst($this->tenant->school_type ?? '—'))
            ->line('**Estimated students:** ' . number_format($this->tenant->estimated_students ?? 0))
            ->line('**Contact:** ' . $this->tenant->contact_email . ' / ' . $this->tenant->contact_phone)
            ->action('Review & Approve', $approveUrl)
            ->line('Log in to the super admin portal to approve or reject this registration.');
    }
}
