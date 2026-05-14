<?php

namespace App\Notifications;

use App\Models\Admission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewAdmissionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Admission $admission) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $admission  = $this->admission;
        $reviewUrl  = url("/admissions/{$admission->id}"); // relative — resolved per tenant domain

        return (new MailMessage)
            ->subject("New Admission Application — {$admission->full_name}")
            ->greeting("Hello,")
            ->line("A new admission application has been submitted on your school website.")
            ->line("**Applicant:** {$admission->full_name}")
            ->line("**Class Applying For:** " . ($admission->class_applying_for ?? 'Not specified'))
            ->line("**Guardian:** {$admission->guardian_name} · {$admission->guardian_phone}")
            ->action('Review Application', $reviewUrl)
            ->line("Log in to your admin dashboard to accept or reject this application.");
    }
}
