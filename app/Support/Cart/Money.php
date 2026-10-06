<?php

namespace App\Support\Cart;

/**
 * Umrechnung zwischen Euro-Beträgen und Cents ohne Float-Rundungsfehler.
 */
final class Money
{
    /**
     * Wandelt "149.5", "149,50", 149.5 oder null in Cents um.
     */
    public static function toCents(string|int|float|null $amount): int
    {
        $normalized = str_replace(',', '.', (string) $amount);
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    /**
     * Formatiert Cents als "149.50" (Punkt, zwei Nachkommastellen, ohne Tausender).
     */
    public static function format(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
