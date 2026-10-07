<?php

namespace App\Models;

use App\Support\Catalog\Categories;
use App\Support\Catalog\Category;
use App\Support\Catalog\Specialties;
use App\Support\Catalog\Specialty;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $manufacturer_id
 * @property string|null $category_slug Slug aus config/categories.php
 * @property list<string>|null $specialties Slugs aus config/specialties.php
 * @property string $name
 * @property string|null $description
 * @property list<array{label: string, value: string}>|null $technical_specs
 * @property string $price
 * @property bool $is_available
 * @property float|string|null $ratings_avg_stars nur mit withAvg('ratings', 'stars') geladen
 */
#[Fillable(['manufacturer_id', 'category_slug', 'specialties', 'name', 'description', 'technical_specs', 'price', 'is_available'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
            'technical_specs' => 'array',
            'specialties' => 'array',
        ];
    }

    /**
     * Kategorie aus config/categories.php (kein DB-Bezug).
     */
    public function category(): ?Category
    {
        return Categories::find($this->category_slug);
    }

    /**
     * Fachrichtungen aus config/specialties.php in Config-Reihenfolge.
     *
     * @return list<Specialty>
     */
    public function specialtyList(): array
    {
        return Specialties::findMany($this->specialties ?? []);
    }

    /**
     * @return BelongsTo<Manufacturer, $this>
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<Rating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class)->latest();
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }
}
