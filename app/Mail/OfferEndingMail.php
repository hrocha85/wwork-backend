<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OfferEndingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $ownerName,
        public string $agencyName,
        public bool $annual,
        public string $today,
        public string $next,
        public string $date,
        public string $manageUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.offer_ending.subject', ['date' => $this->date]));
    }

    public function content(): Content
    {
        return new Content(text: 'mail.offer-ending');
    }
}
