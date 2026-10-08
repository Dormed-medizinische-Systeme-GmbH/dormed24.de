<?php

namespace App\Support\Newsletter;

use App\Support\HasStatusBadge;
use App\Support\StatusTone;

enum NewsletterStatus: string
{
    use HasStatusBadge;

    case Draft = 'draft';
    case Sent = 'sent';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Sent => 'Verschickt',
        };
    }

    public function tone(): StatusTone
    {
        return match ($this) {
            self::Draft => StatusTone::Neutral,
            self::Sent => StatusTone::Success,
        };
    }
}
