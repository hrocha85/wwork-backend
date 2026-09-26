<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OwnerWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $ownerName,
        public string $agencyName,
        public string $emailAddress,
        public string $temporaryPassword,
        public string $appUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your WWork account is ready');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.owner-welcome');
    }
}
