<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A personal message written by a staff member. It is sent from the
 * institution's address, but replies go straight back to the person who wrote it.
 */
class DirectMessage extends Mailable
{
    public function __construct(
        public User $sender,
        public string $recipientName,
        public string $subjectLine,
        public string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            replyTo: [new Address($this->sender->email, $this->sender->name)],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.direct-message');
    }
}
