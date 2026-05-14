<?php

namespace App\Mail;

use App\Models\Admission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the school admin when a new admission application is submitted.
 * Pass $isApplicantCopy = true to send the confirmation copy to the guardian.
 */
class AdmissionReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Admission $admission,
        public readonly bool      $isApplicantCopy = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isApplicantCopy
                ? 'Application Received — ' . ($this->admission->tenant?->name ?? 'School')
                : 'New Admission Application #' . $this->admission->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.admission-received',
            with: [
                'admission'       => $this->admission,
                'school'          => $this->admission->tenant,
                'isApplicantCopy' => $this->isApplicantCopy,
            ],
        );
    }
}
