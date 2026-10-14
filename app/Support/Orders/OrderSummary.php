<?php

namespace App\Support\Orders;

use App\Models\Order;
use App\Support\Cart\Money;

/**
 * Positionen und Summen einer gespeicherten Bestellung in derselben Form wie
 * der Warenkorb. Genutzt von Betreiber-Mail, Erfolgsseite, Kundenkonto und
 * Admin, damit die MwSt.-Rechnung an einer Stelle steht.
 *
 * @phpstan-type OrderSummaryArray array{items: array<int, array<string, mixed>>, subtotal: string, shipping_method: string|null, shipping_total: string, vat_rate: int, vat_amount: string, total: string}
 */
final class OrderSummary
{
    /**
     * @return OrderSummaryArray
     */
    public static function fromOrder(Order $order): array
    {
        $order->loadMissing('items');

        $vatRate = (int) config('shop.cart.vat_rate', 19);
        $totalCents = (int) round((float) $order->total_amount * 100);
        $shippingCents = (int) round((float) $order->shipping_amount * 100);
        $vatAmountCents = $totalCents - Money::netCents($totalCents, $vatRate);

        return [
            'items' => $order->items->map(fn ($item): array => [
                'name' => $item->product_name,
                'product_number' => $item->product_id ?? '—',
                'quantity' => $item->quantity,
                'unit_price' => number_format((float) $item->unit_price, 2, '.', ''),
                'line_total' => number_format((float) $item->unit_price * $item->quantity, 2, '.', ''),
            ])->all(),
            'subtotal' => Money::format($totalCents - $shippingCents),
            // Snapshot aus der Bestellung, nie aus config/shipping.php.
            'shipping_method' => $order->shipping_method_name,
            'shipping_total' => Money::format($shippingCents),
            'vat_rate' => $vatRate,
            'vat_amount' => Money::format($vatAmountCents),
            'total' => Money::format($totalCents),
        ];
    }
}
