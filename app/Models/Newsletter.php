<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\NewsletterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $subject
 * @property string|null $preheader
 * @property string $status
 * @property array<string, mixed>|null $design_json
 * @property string|null $mjml
 * @property string|null $html
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['name', 'subject', 'preheader', 'design_json', 'mjml', 'html'])]
class Newsletter extends Model
{
    /** @use HasFactory<NewsletterFactory> */
    use HasFactory;

    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_SENT = 'sent';

    protected function casts(): array
    {
        return [
            'design_json' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function hasContent(): bool
    {
        return filled($this->html);
    }
}
