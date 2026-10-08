<?php

namespace App\Models;

use App\Support\Magazine\ArticleStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Magazin-Artikel. Sichtbar im Shop, sobald published_at erreicht ist.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $category
 * @property string|null $teaser
 * @property string $content
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['title', 'slug', 'category', 'teaser', 'content', 'published_at'])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    /**
     * Kategorien: Schlüssel => Anzeigename.
     *
     * @var array<string, string>
     */
    public const array CATEGORIES = [
        'ratgeber' => 'Ratgeber',
        'aktuelles' => 'Aktuelles',
        'praxiswissen' => 'Praxiswissen',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * Nur Artikel, deren Veröffentlichungszeitpunkt erreicht ist.
     *
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function status(): ArticleStatus
    {
        if ($this->published_at === null) {
            return ArticleStatus::Draft;
        }

        return $this->published_at->isFuture() ? ArticleStatus::Scheduled : ArticleStatus::Published;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    /**
     * Inhalt als HTML. Eingebettetes HTML im Markdown wird entfernt und
     * unsichere Links (javascript:, data:) werden nicht verlinkt.
     */
    public function contentHtml(): string
    {
        return Str::markdown($this->content, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Freier Slug aus dem Titel, bei Kollision mit Zähler ("titel-2").
     */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'artikel';
        $slug = $base;

        $taken = fn (string $candidate): bool => self::query()
            ->where('slug', $candidate)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();

        for ($suffix = 2; $taken($slug); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
