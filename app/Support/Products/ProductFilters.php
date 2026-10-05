<?php

namespace App\Support\Products;

use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Shared filter resolution for the public product listings
 * (ProductController::index and CategoryController::show).
 *
 * Hersteller filtern echt (manufacturer_id). Fachrichtungen sind bewusst
 * "tot" implementiert: Das Datenmodell kennt sie noch nicht, deshalb liefern
 * die Props leere Listen und der Query-Parameter `fachrichtung` wird ignoriert.
 */
final class ProductFilters
{
    /**
     * @param  list<int>  $manufacturerIds
     * @param  list<array{id: int, name: string, count: int}>  $manufacturerOptions
     */
    private function __construct(
        public readonly array $manufacturerIds,
        public readonly array $manufacturerOptions,
        public readonly int $unfilteredTotal,
    ) {}

    /**
     * Resolves the selectable manufacturers (with product counts) from the
     * listing scope *before* the manufacturer filter is applied, and keeps
     * only requested ids that actually occur in that scope.
     *
     * @param  Builder<Product>  $scope
     */
    public static function fromRequest(Request $request, Builder $scope): self
    {
        $countsByManufacturer = $scope->clone()
            ->reorder()
            ->whereNotNull('manufacturer_id')
            ->selectRaw('manufacturer_id, count(*) as aggregate')
            ->groupBy('manufacturer_id')
            ->pluck('aggregate', 'manufacturer_id');

        $manufacturerOptions = Manufacturer::query()
            ->whereKey($countsByManufacturer->keys()->all())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Manufacturer $manufacturer) => [
                'id' => $manufacturer->id,
                'name' => $manufacturer->name,
                'count' => (int) $countsByManufacturer[$manufacturer->id],
            ])
            ->values()
            ->all();

        $availableIds = array_column($manufacturerOptions, 'id');

        $manufacturerIds = collect(Arr::wrap($request->query('hersteller', [])))
            ->filter(fn (mixed $id) => is_scalar($id) && ctype_digit((string) $id))
            ->map(fn (mixed $id) => (int) $id)
            ->filter(fn (int $id) => in_array($id, $availableIds, true))
            ->unique()
            ->values()
            ->all();

        return new self($manufacturerIds, $manufacturerOptions, $scope->clone()->reorder()->count());
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function apply(Builder $query): Builder
    {
        return $query->when(
            $this->manufacturerIds !== [],
            fn (Builder $query) => $query->whereIn('manufacturer_id', $this->manufacturerIds),
        );
    }

    /**
     * @return array{
     *     filters: array{manufacturers: list<int>, specialties: list<string>},
     *     filterOptions: array{
     *         manufacturers: list<array{id: int, name: string, count: int}>,
     *         specialties: list<array{id: string, name: string, count: int}>,
     *         total: int
     *     }
     * }
     */
    public function toProps(): array
    {
        return [
            'filters' => [
                'manufacturers' => $this->manufacturerIds,
                'specialties' => [],
            ],
            'filterOptions' => [
                'manufacturers' => $this->manufacturerOptions,
                'specialties' => [],
                'total' => $this->unfilteredTotal,
            ],
        ];
    }
}
