<?php

namespace App\Support\Products;

use App\Models\Manufacturer;
use App\Models\Product;
use App\Support\Catalog\Specialties;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Shared filter resolution for the public product listings
 * (ProductController::index and CategoryController::show).
 *
 * Hersteller (`hersteller[]`) und Fachrichtungen (`fachrichtung[]`) filtern
 * jeweils mit ODER innerhalb der Gruppe und UND zwischen den Gruppen.
 * Angeboten wird nur, was im Listen-Scope vorkommt; die Anzahl je Option
 * berücksichtigt den aktiven Filter der jeweils anderen Gruppe.
 */
final class ProductFilters
{
    /**
     * @param  list<int>  $manufacturerIds
     * @param  list<string>  $specialtySlugs
     * @param  list<array{id: int, name: string, count: int}>  $manufacturerOptions
     * @param  list<array{id: string, name: string, count: int}>  $specialtyOptions
     */
    private function __construct(
        public readonly array $manufacturerIds,
        public readonly array $specialtySlugs,
        public readonly array $manufacturerOptions,
        public readonly array $specialtyOptions,
        public readonly int $unfilteredTotal,
        public readonly int $totalWithSpecialtyFilter,
    ) {}

    /**
     * @param  Builder<Product>  $scope
     */
    public static function fromRequest(Request $request, Builder $scope): self
    {
        $manufacturerNames = self::manufacturerNamesIn($scope);
        $availableSpecialties = Specialties::normalize(self::specialtyValuesIn($scope)->flatten());

        $manufacturerIds = array_values(collect(Arr::wrap($request->query('hersteller', [])))
            ->filter(fn (mixed $id) => is_scalar($id) && ctype_digit((string) $id))
            ->map(fn (mixed $id) => (int) $id)
            ->filter(fn (int $id) => $manufacturerNames->has($id))
            ->unique()
            ->all());

        $specialtySlugs = array_values(array_intersect(
            Specialties::normalize(Arr::wrap($request->query('fachrichtung', []))),
            $availableSpecialties,
        ));

        $withSpecialties = self::applySpecialties($scope->clone()->reorder(), $specialtySlugs);
        $withManufacturers = self::applyManufacturers($scope->clone()->reorder(), $manufacturerIds);

        $manufacturerCounts = $withSpecialties->clone()
            ->whereNotNull('manufacturer_id')
            ->selectRaw('manufacturer_id, count(*) as aggregate')
            ->groupBy('manufacturer_id')
            ->pluck('aggregate', 'manufacturer_id');

        $manufacturerOptions = array_values($manufacturerNames
            ->map(fn (string $name, int $id) => [
                'id' => $id,
                'name' => $name,
                'count' => (int) ($manufacturerCounts[$id] ?? 0),
            ])
            ->all());

        $specialtyCounts = self::specialtyValuesIn($withManufacturers)->flatten()->countBy();

        $specialtyOptions = array_map(
            fn ($specialty) => [
                'id' => $specialty->slug,
                'name' => $specialty->name,
                'count' => (int) ($specialtyCounts[$specialty->slug] ?? 0),
            ],
            Specialties::findMany($availableSpecialties),
        );

        return new self(
            $manufacturerIds,
            $specialtySlugs,
            $manufacturerOptions,
            $specialtyOptions,
            $scope->clone()->reorder()->count(),
            $withSpecialties->count(),
        );
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function apply(Builder $query): Builder
    {
        return self::applySpecialties(
            self::applyManufacturers($query, $this->manufacturerIds),
            $this->specialtySlugs,
        );
    }

    /**
     * @return array{
     *     filters: array{manufacturers: list<int>, specialties: list<string>},
     *     filterOptions: array{
     *         manufacturers: list<array{id: int, name: string, count: int}>,
     *         specialties: list<array{id: string, name: string, count: int}>,
     *         total: int,
     *         totalWithSpecialtyFilter: int
     *     }
     * }
     */
    public function toProps(): array
    {
        return [
            'filters' => [
                'manufacturers' => $this->manufacturerIds,
                'specialties' => $this->specialtySlugs,
            ],
            'filterOptions' => [
                'manufacturers' => $this->manufacturerOptions,
                'specialties' => $this->specialtyOptions,
                'total' => $this->unfilteredTotal,
                'totalWithSpecialtyFilter' => $this->totalWithSpecialtyFilter,
            ],
        ];
    }

    /**
     * Hersteller, die im Scope vorkommen, nach Name sortiert.
     *
     * @param  Builder<Product>  $scope
     * @return Collection<int, string>
     */
    private static function manufacturerNamesIn(Builder $scope): Collection
    {
        $ids = $scope->clone()
            ->reorder()
            ->whereNotNull('manufacturer_id')
            ->distinct()
            ->pluck('manufacturer_id');

        /** @var Collection<int, string> $names */
        $names = Manufacturer::query()
            ->whereKey($ids->all())
            ->orderBy('name')
            ->pluck('name', 'id');

        return $names;
    }

    /**
     * Fachrichtungs-Listen aller Produkte im Scope (ein Eintrag je Produkt).
     *
     * @param  Builder<Product>  $scope
     * @return Collection<int, list<mixed>>
     */
    private static function specialtyValuesIn(Builder $scope): Collection
    {
        return $scope->clone()
            ->whereNotNull('specialties')
            ->pluck('specialties')
            ->map(fn (mixed $value): array => array_values(
                is_string($value) ? (array) json_decode($value, true) : (array) $value
            ))
            ->values();
    }

    /**
     * @param  Builder<Product>  $query
     * @param  list<int>  $manufacturerIds
     * @return Builder<Product>
     */
    private static function applyManufacturers(Builder $query, array $manufacturerIds): Builder
    {
        return $query->when(
            $manufacturerIds !== [],
            fn (Builder $query) => $query->whereIn('manufacturer_id', $manufacturerIds),
        );
    }

    /**
     * @param  Builder<Product>  $query
     * @param  list<string>  $specialtySlugs
     * @return Builder<Product>
     */
    private static function applySpecialties(Builder $query, array $specialtySlugs): Builder
    {
        return $query->when(
            $specialtySlugs !== [],
            fn (Builder $query) => $query->where(function (Builder $query) use ($specialtySlugs): void {
                foreach ($specialtySlugs as $slug) {
                    $query->orWhereJsonContains('specialties', $slug);
                }
            }),
        );
    }
}
