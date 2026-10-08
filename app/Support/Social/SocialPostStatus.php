<?php

namespace App\Support\Social;

use App\Support\HasStatusBadge;
use App\Support\StatusTone;

/**
 * Status eines Social-Beitrags. Veröffentlicht wird noch nicht; Published
 * und Failed sind für den späteren Versand vorgesehen.
 */
enum SocialPostStatus: string
{
    use HasStatusBadge;

    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Scheduled => 'Geplant',
            self::Published => 'Veröffentlicht',
            self::Failed => 'Fehlgeschlagen',
        };
    }

    public function tone(): StatusTone
    {
        return match ($this) {
            self::Draft => StatusTone::Neutral,
            self::Scheduled => StatusTone::Info,
            self::Published => StatusTone::Success,
            self::Failed => StatusTone::Danger,
        };
    }
}
