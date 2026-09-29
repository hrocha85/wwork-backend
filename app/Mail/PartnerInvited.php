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

    public function __construct(
        public Invite $invite,
        public string $url,
    ) {}

    public function envelope(): Envelope
    {
        $this->invite->loadMissing('agency');

        return new Envelope(subject: __('mail.invite.subject', ['agency' => $this->invite->agency->name]));
    }

    public function content(): Content
    {
        $this->invite->loadMissing(['agency', 'inviter']);

        return new Content(
            view: 'mail.partner-invite',
            text: 'mail.partner-invite-text',
            with: [
                'agency' => $this->invite->agency->name,
                'inviter' => $this->invite->inviter?->name,
                'url' => $this->url,
                'days' => Invite::TTL_DAYS,
            ],
        );
    }
}
