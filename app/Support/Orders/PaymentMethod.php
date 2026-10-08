<?php

namespace App\Support\Orders;

/**
 * Bezahlart einer Bestellung. Labels und Reihenfolge im Checkout stehen in
 * config/shop.php (Schlüssel = Wert dieses Enums).
 */
enum PaymentMethod: string
{
    case Invoice = 'invoice';
    case Paypal = 'paypal';
}
