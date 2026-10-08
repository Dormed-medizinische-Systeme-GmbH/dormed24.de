<?php

namespace App\Support\Orders;

use App\Support\HasStatusBadge;
use App\Support\StatusTone;

/**
 * Bestellstatus. Es gibt bewusst keinen Versand- oder Bearbeitungsstatus
 * (.docs/adr/0002-kein-fulfillment-status.md).
 *
 * Erlaubte Übergänge stehen in reachableFrom(); gesetzt wird der Status nur
 * über den OrderManager.
 */
enum OrderStatus: string
{
    use HasStatusBadge;

    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /**
     * Status, aus denen dieser Status erreichbar ist.
     *
     * - Paid auch aus Failed/Cancelled: Kommt das Geld verspätet doch an,
     *   muss der Status dazu passen (.docs/adr/0003-…).
     * - Refunded ist endgültig, Paid kann nur noch erstattet werden.
     * - Cancelled nur aus Pending: abgebrochene, nie bezahlte PayPal-Versuche.
     *
     * @return list<self>
     */
    public function reachableFrom(): array
    {
        return match ($this) {
            self::Pending => [],
            self::Paid, self::Failed => [self::Pending, self::Failed, self::Cancelled],
            self::Cancelled => [self::Pending],
            self::Refunded => [self::Pending, self::Paid, self::Failed, self::Cancelled],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($this, $target->reachableFrom(), true);
    }

    /**
     * Bezahlt oder erstattet: Spät eintreffende Fehlermeldungen von PayPal
     * ändern daran nichts mehr.
     */
    public function isSettled(): bool
    {
        return $this === self::Paid || $this === self::Refunded;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Ausstehend',
            self::Paid => 'Bezahlt',
            self::Failed => 'Fehlgeschlagen',
            self::Cancelled => 'Storniert',
            self::Refunded => 'Erstattet',
        };
    }

    public function tone(): StatusTone
    {
        return match ($this) {
            self::Pending => StatusTone::Warning,
            self::Paid => StatusTone::Success,
            self::Failed, self::Cancelled, self::Refunded => StatusTone::Danger,
        };
    }
}
