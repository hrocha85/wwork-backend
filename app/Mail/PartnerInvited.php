<?php

namespace App\Mail;

use App\Models\Invite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerInvited extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invite $invite) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'WWork invite');
    }

    public function content(): Content
    {
        $this->invite->loadMissing('agency');

        return new Content(
            text: 'mail.partner-invite',
            with: [
                'agency' => $this->invite->agency->name,
                'url' => rtrim((string) config('wwork.frontend_url'), '/').'/invites/'.$this->invite->token,
            ],
        );
    }
}
