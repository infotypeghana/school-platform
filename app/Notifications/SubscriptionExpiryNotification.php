<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly string $type
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = $this->subscription->tenant;
        $term   = $this->subscription->term;
        $currency = config('billing.currency', 'GHS');
        $amount = "{$currency} " . number_format($this->subscription->amount, 2);
        $termLabel = $term?->term_name . ' ' . $term?->academicYear?->year_label;
        $renewUrl  = url('/pay/' . $tenant->slug);
        $graceDays = $this->subscription->graceDaysRemaining();

        return match ($this->type) {

            '14_days_before' => (new MailMessage)
                ->subject("Subscription Renewal Reminder — {$tenant->name}")
                ->greeting("Hello, {$tenant->name} Admin")
                ->line("Your **{$termLabel}** subscription expires in **14 days**.")
                ->line("Renewing early ensures uninterrupted access for your school and parents.")
                ->action("Renew Now — {$amount}", $renewUrl)
                ->line("Questions? Contact us at support@schoolms.com.gh"),

            '7_days_before' => (new MailMessage)
                ->subject("⚠️ 7 Days to Renewal — {$tenant->name}")
                ->greeting("Hello, {$tenant->name} Admin")
                ->line("Your **{$termLabel}** subscription expires in **7 days**.")
                ->line("After expiry, a 5-day grace period applies before the platform is locked.")
                ->action("Renew Now — {$amount}", $renewUrl),

            '2_days_before' => (new MailMessage)
                ->subject("🔴 2 Days Left — Renew Before Lockout")
                ->greeting("Urgent: {$tenant->name}")
                ->line("Your **{$termLabel}** subscription expires in **2 days**.")
                ->line("After expiry, you will have a 5-day grace period before your admin dashboard and public website are locked.")
                ->action("Renew Now — {$amount}", $renewUrl),

            'expiry_day' => (new MailMessage)
                ->subject("Subscription Expired — Grace Period Started")
                ->greeting("{$tenant->name} — Action Required")
                ->line("Your **{$termLabel}** subscription has expired today.")
                ->line("You have a **5-day grace period**. Your platform remains accessible until grace ends.")
                ->line("Pay now to avoid a full lock on your admin dashboard and public website.")
                ->action("Pay Now — {$amount}", $renewUrl),

            'grace_3_days_left' => (new MailMessage)
                ->subject("🔴 3 Days Until Platform Lock — {$tenant->name}")
                ->greeting("{$tenant->name} — Urgent")
                ->line("Your grace period ends in **3 days**.")
                ->line("After that, your admin dashboard and public website will be fully locked.")
                ->line("Parents will see a lock screen instead of your school website.")
                ->action("Restore Access Now — {$amount}", $renewUrl),

            'grace_1_day_left' => (new MailMessage)
                ->subject("🔴 FINAL WARNING: Platform Locks Tomorrow")
                ->greeting("{$tenant->name} — Critical")
                ->line("Your grace period ends **tomorrow**.")
                ->line("Your entire platform (admin + website) will be locked unless you pay today.")
                ->action("Pay Before Midnight — {$amount}", $renewUrl),

            'grace_expired_locked' => (new MailMessage)
                ->subject("🔒 Platform Locked — Payment Required")
                ->greeting("{$tenant->name}")
                ->line("Your grace period has ended. Your platform is now **fully locked**.")
                ->line("All your data is safe and preserved. Access is restored immediately after payment.")
                ->action("Unlock Now — {$amount}", $renewUrl)
                ->line("Instant reactivation · No manual approval needed"),

            'payment_confirmed' => (new MailMessage)
                ->subject("✅ Payment Confirmed — Access Restored")
                ->greeting("Welcome back, {$tenant->name}!")
                ->line("Your **{$termLabel}** subscription has been activated successfully.")
                ->line("Your admin dashboard and public website are now fully accessible.")
                ->action("Go to Dashboard", url('/'))
                ->line('Thank you for renewing with ' . config('app.name') . '.'),

            default => (new MailMessage)
                ->subject("Subscription Update — {$tenant->name}")
                ->line("There is an update regarding your subscription.")
                ->action("View Account", $renewUrl),
        };
    }
}
