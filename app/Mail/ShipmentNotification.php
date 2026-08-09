<?php // app/Mail/ShipmentNotification.php

namespace App\Mail;

use App\Domain\Order\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShipmentNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Order {$this->order->order_number} is on its way");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.shipment-notification',
            with: ['order' => $this->order],
        );
    }
}
