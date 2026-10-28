<?php

namespace App\Mail;

use App\Models\SponsorDealSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SponsorDealSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SponsorDealSubmission $submission)
    {
    }

    public function envelope(): Envelope
    {
        $eventName = $this->submission->event?->display_name ?? 'WordCamp';

        return new Envelope(
            subject: "New Sponsor Deal Submission: {$this->submission->sponsor_name} ({$eventName})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sponsor-deal-submitted',
        );
    }
}
