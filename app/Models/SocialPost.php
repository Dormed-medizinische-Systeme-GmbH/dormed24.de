<?php

namespace App\Models;

use App\Support\Social\SocialPostStatus;
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
 * @property SocialPostStatus $status
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
            'status' => SocialPostStatus::class,
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function isEditable(): bool
    {
        return $this->status !== SocialPostStatus::Published;
    }
}
