<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\Images\ImageStore;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $product_id
 * @property string $path
 * @property int|null $width
 * @property int|null $height
 * @property int $sort_order
 * @property-read string $url
 * @property-read string|null $avif_url
 */
#[Fillable(['product_id', 'path', 'width', 'height', 'sort_order'])]
class ProductImage extends Model
{
    protected $appends = ['url', 'avif_url'];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * URL der WebP-Datei (Fallback-`src` und `og:image`).
     */
    public function getUrlAttribute(): string
    {
        return $this->publicUrl($this->path);
    }

    /**
     * URL der AVIF-Datei neben dem WebP; `null` für Altbestand, der noch
     * nicht umgewandelt wurde.
     */
    public function getAvifUrlAttribute(): ?string
    {
        $avifPath = ImageStore::avifPath($this->path);

        return $avifPath === null ? null : $this->publicUrl($avifPath);
    }

    private function publicUrl(string $path): string
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($path);
    }
}
