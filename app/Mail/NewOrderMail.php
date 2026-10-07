<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewOrderMail extends Mailable
{
    /**
     * @param  array{items: array<int, array<string, mixed>>, subtotal: string, shipping_total: string, vat_rate: int, vat_amount: string, total: string}  $cart  OrderManager::summaryFromOrder()
     */
    public function __construct(
        public readonly Order $order,
        public readonly array $cart,
        public readonly Customer $customer,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Neue Bestellung #'.$this->order->id.' — dormed24.de',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-order',
            with: [
                'adminUrl' => route('admin.dashboard'),
            ],
        );
    }
}
