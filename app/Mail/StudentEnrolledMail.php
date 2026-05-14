<?php

namespace App\Mail;

use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the guardian when a student is successfully enrolled (either via
 * the admission workflow or a direct admin creation).
 *
 * This gives parents formal confirmation of their child's admission number,
 * class placement, and a point of contact for questions.
 */
class StudentEnrolledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Student $student,
        public readonly Tenant  $tenant,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Enrolment Confirmed — ' . $this->student->full_name . ' · ' . $this->tenant->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.student-enrolled',
            with: [
                'student' => $this->student,
                'school'  => $this->tenant,
            ],
        );
    }
}
