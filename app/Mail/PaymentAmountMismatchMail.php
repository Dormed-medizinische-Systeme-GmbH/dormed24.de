<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Operator alert: a PayPal capture completed with an amount that does not
 * match the order total. The money has already been collected at PayPal
 * while the order was marked failed - this always needs a manual look.
 */
class PaymentAmountMismatchMail extends Mailable
{
    public function __construct(
        public readonly Payment $payment,
        public readonly string $capturedAmount,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'PayPal-Betragsabweichung bei Bestellung #'.$this->payment->order->id.' — dormed24.de',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-amount-mismatch',
            with: [
                'order' => $this->payment->order,
                'adminUrl' => route('admin.orders.show', $this->payment->order),
            ],
        );
    }
}
