<?php

namespace App\Support;

/**
 * Farbton eines Status-Badges. Die Farben selbst stehen nur im Frontend
 * (resources/js/lib/status.ts).
 */
enum StatusTone: string
{
    case Neutral = 'neutral';
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';
}
