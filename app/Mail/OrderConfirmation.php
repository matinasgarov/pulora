<?php // app/Mail/OrderConfirmation.php

namespace App\Mail;

use App\Domain\Order\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Order {$this->order->order_number} confirmed");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.order-confirmation',
            with: ['order' => $this->order->load('items')],
        );
    }
}
