<?php

namespace App\Support\Orders;

/**
 * Status einer PayPal-Zahlung. Die Werte entsprechen den Status, die PayPal
 * meldet, plus MISMATCH (eingezogen, aber Betrag/Währung weichen ab).
 */
enum PaymentStatus: string
{
    case Created = 'CREATED';
    case Approved = 'APPROVED';
    case Pending = 'PENDING';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
    case Mismatch = 'MISMATCH';
    case Refunded = 'REFUNDED';
    case Reversed = 'REVERSED';

    /**
     * Geld ist bei PayPal eingezogen und kann erstattet werden.
     */
    public function isRefundable(): bool
    {
        return $this === self::Completed || $this === self::Mismatch;
    }
}
