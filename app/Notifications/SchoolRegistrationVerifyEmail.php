<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the school contact email during self-registration Phase 1.
 *
 * The recipient must click the verification link before a pending Tenant
 * record is created.  This prevents junk / fake-email registrations from
 * polluting the super-admin review queue.
 *
 * The link is valid for 60 minutes (VERIFY_TTL in TenantRegistrationController).
 */
class SchoolRegistrationVerifyEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $contactName,
        private readonly string $schoolName,
        private readonly string $verifyUrl,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $appName = config('app.name');

        return (new MailMessage())
            ->subject("Verify your school registration — {$appName}")
            ->greeting("Hello {$this->contactName},")
            ->line("Thank you for registering **{$this->schoolName}** on {$appName}.")
            ->line('Please click the button below to verify your email address and submit your application for review.')
            ->action('Verify Email Address', $this->verifyUrl)
            ->line('This link will expire in **60 minutes**. If you did not request this, you can safely ignore this email.')
            ->salutation("The {$appName} Team");
    }
}
