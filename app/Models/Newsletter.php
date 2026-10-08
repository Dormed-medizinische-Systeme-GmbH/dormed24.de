<?php

namespace App\Models;

use App\Support\Newsletter\NewsletterStatus;
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
 * @property NewsletterStatus $status
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

    protected function casts(): array
    {
        return [
            'design_json' => 'array',
            'status' => NewsletterStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function isEditable(): bool
    {
        return $this->status === NewsletterStatus::Draft;
    }

    public function hasContent(): bool
    {
        return filled($this->html);
    }
}
