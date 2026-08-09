<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentAnomaly extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $reason,
        public string $reference,
        public ?string $ip = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Payment anomaly: {$this->reason}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.payment-anomaly');
    }
}
