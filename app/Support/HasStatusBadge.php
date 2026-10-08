<?php

namespace App\Support;

/**
 * Für Status-Enums, die im Frontend als Badge erscheinen: Jeder Fall
 * liefert sein Label und seinen Ton selbst, badges() die ganze Zuordnung
 * als Prop (StatusMap in resources/js/lib/status.ts).
 */
trait HasStatusBadge
{
    abstract public function label(): string;

    abstract public function tone(): StatusTone;

    /**
     * @return array<string, array{label: string, tone: string}>
     */
    public static function badges(): array
    {
        $badges = [];

        foreach (self::cases() as $case) {
            $badges[$case->value] = ['label' => $case->label(), 'tone' => $case->tone()->value];
        }

        return $badges;
    }
}
