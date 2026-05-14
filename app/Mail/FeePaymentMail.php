<?php

namespace App\Mail;

use App\Models\Fee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the guardian's email when a fee payment is recorded.
 */
class FeePaymentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Fee   $fee,
        public readonly float $amountJustPaid,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Received — ' . ($this->fee->student?->full_name ?? 'Student'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.fee-payment',
            with: [
                'fee'            => $this->fee,
                'student'        => $this->fee->student,
                'school'         => app()->bound('currentTenant') ? app('currentTenant') : $this->fee->student?->tenant,
                'amountJustPaid' => $this->amountJustPaid,
            ],
        );
    }
}
