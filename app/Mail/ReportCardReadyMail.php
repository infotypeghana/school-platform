<?php

namespace App\Mail;

use App\Models\ReportCard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the guardian's email when a student's report card PDF is ready.
 */
class ReportCardReadyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ReportCard $reportCard,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Report Card Ready — ' . ($this->reportCard->student?->full_name ?? 'Student'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.report-card-ready',
            with: [
                'reportCard' => $this->reportCard,
                'student'    => $this->reportCard->student,
                'term'       => $this->reportCard->term,
                'school'     => app()->bound('currentTenant') ? app('currentTenant') : null,
            ],
        );
    }
}
