<?php

namespace App\Support\Orders;

/**
 * Bezahlart einer Bestellung. Paypal, Card und Sepa laufen alle über PayPal
 * (je ein eigener Button im Checkout, siehe fundingSource()); Invoice wird
 * ohne Zahlungsanbieter abgeschlossen.
 */
enum PaymentMethod: string
{
    case Invoice = 'invoice';
    case Paypal = 'paypal';
    case Card = 'card';
    case Sepa = 'sepa';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Kauf auf Rechnung',
            self::Paypal => 'PayPal',
            self::Card => 'Kreditkarte',
            self::Sepa => 'Lastschrift',
        };
    }

    /**
     * Zahlart-Kennung des PayPal-JS-SDK (fundingSource) oder null, wenn die
     * Bezahlart nicht über PayPal läuft.
     */
    public function fundingSource(): ?string
    {
        return $this->isPayPalFunded() ? $this->value : null;
    }

    public function isPayPalFunded(): bool
    {
        return $this !== self::Invoice;
    }

    /**
     * @return list<self>
     */
    public static function payPalFunded(): array
    {
        return array_values(array_filter(self::cases(), fn (self $method): bool => $method->isPayPalFunded()));
    }
}
