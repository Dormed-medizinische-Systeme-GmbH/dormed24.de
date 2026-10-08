<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\Order;
use App\Support\ShopContact;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderConfirmationMail extends Mailable
{
    public function __construct(
        public readonly Order $order,
        public readonly Customer $customer,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ihre Bestellbestätigung #'.$this->order->id.' — dormed24.de',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-confirmation',
            with: [
                'shop' => app(ShopContact::class),
            ],
        );
    }
}
