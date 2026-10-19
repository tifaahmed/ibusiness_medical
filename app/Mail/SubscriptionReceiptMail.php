<?php

namespace App\Mail;

use App\Models\MembershipSubscription;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The receipt for a paid membership: what was paid, how, and until when the card is good. */
class SubscriptionReceiptMail extends Mailable
{
    use SerializesModels;

    public function __construct(public MembershipSubscription $subscription) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Deilar membership receipt — card '.$this->subscription->membership?->membership_number);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.subscription-receipt');
    }
}
