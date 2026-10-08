<?php

namespace App\Support\Magazine;

use App\Support\HasStatusBadge;
use App\Support\StatusTone;

/**
 * Status eines Magazin-Artikels, abgeleitet aus published_at.
 */
enum ArticleStatus: string
{
    use HasStatusBadge;

    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Scheduled => 'Geplant',
            self::Published => 'Veröffentlicht',
        };
    }

    public function tone(): StatusTone
    {
        return match ($this) {
            self::Draft => StatusTone::Neutral,
            self::Scheduled => StatusTone::Info,
            self::Published => StatusTone::Success,
        };
    }
}
