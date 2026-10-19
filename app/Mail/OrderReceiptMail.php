<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The purchase receipt: what was bought, what it came to, and how it was paid. */
class OrderReceiptMail extends Mailable
{
    use SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your receipt — order '.$this->order->order_code);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-receipt');
    }
}
