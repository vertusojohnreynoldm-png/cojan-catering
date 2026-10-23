<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

/**
 * Catering downpayment emails — one Mailable, one template, four moments:
 * received (pay by the deadline), confirmed (downpayment paid, balance on
 * the event day), rejected (please upload again) and expired (cancelled).
 */
class BookingPaymentMail extends Mailable
{
    use Queueable, SerializesModels;

    public const SUBJECTS = [
        'received'  => 'Booking received — pay your downpayment to secure the date',
        'confirmed' => 'Downpayment confirmed — your date is secured',
        'rejected'  => 'We couldn\'t verify your GCash receipt',
        'expired'   => 'Your booking was cancelled — downpayment not received',
    ];

    public function __construct(public Order $order, public string $type)
    {
        if (!array_key_exists($type, self::SUBJECTS)) {
            throw new InvalidArgumentException("Unknown booking payment email [$type].");
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Booking #{$this->order->order_number} — " . self::SUBJECTS[$this->type]);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-payment',
            with: ['order' => $this->order, 'type' => $this->type],
        );
    }
}
