<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SocialPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Social-Media-Beitrag. Bisher nur Planung im Admin; Veröffentlichen
 * auf den Plattformen kommt später.
 *
 * @property int $id
 * @property string $title
 * @property string $content
 * @property list<string> $platforms
 * @property string $status
 * @property CarbonImmutable|null $scheduled_at
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['title', 'content', 'platforms', 'status', 'scheduled_at'])]
class SocialPost extends Model
{
    /** @use HasFactory<SocialPostFactory> */
    use HasFactory;

    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_SCHEDULED = 'scheduled';

    public const string STATUS_PUBLISHED = 'published';

    public const string STATUS_FAILED = 'failed';

    /**
     * Unterstützte Plattformen: Schlüssel => Anzeigename.
     *
     * @var array<string, string>
     */
    public const array PLATFORMS = [
        'linkedin' => 'LinkedIn',
        'facebook' => 'Facebook',
    ];

    /**
     * Längste Textlänge, die alle Plattformen annehmen (LinkedIn: 3.000 Zeichen).
     */
    public const int MAX_CONTENT_LENGTH = 3000;

    protected function casts(): array
    {
        return [
            'platforms' => 'array',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function isEditable(): bool
    {
        return $this->status !== self::STATUS_PUBLISHED;
    }
}
