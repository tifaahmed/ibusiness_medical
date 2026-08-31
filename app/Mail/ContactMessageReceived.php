<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the inbox an enquiry has come in, from whichever public form.
 *
 * `$commercialRegister` is set only for a facility applying to join the
 * network — it is the number sales verifies the applicant against, so it
 * belongs in the mail rather than only in the admin.
 */
class ContactMessageReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $senderPhone,
        public string $body,
        public ?string $commercialRegister = null,
        public ?string $senderName = null,
        public ?string $source = null,
    ) {}

    /**
     * The forms collect no email address, so there is nothing to reply to —
     * the phone number is the only way back to the sender and it goes in the
     * subject line to keep it visible in the inbox list.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New enquiry from '.$this->senderPhone,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.contact-message',
        );
    }
}
