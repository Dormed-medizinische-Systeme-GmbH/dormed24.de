<?php

namespace Tests\Unit;

use App\Support\Products\ProductSort;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProductSortTest extends TestCase
{
    /**
     * @return array<string, array{array<string, string>, array{string, string, string}}>
     */
    public static function sorts(): array
    {
        return [
            'ohne Angabe' => [[], ['name_asc', 'name', 'asc']],
            'Name absteigend' => [['sort' => 'name_desc'], ['name_desc', 'name', 'desc']],
            'Preis aufsteigend' => [['sort' => 'price_asc'], ['price_asc', 'price', 'asc']],
            'Preis absteigend' => [['sort' => 'price_desc'], ['price_desc', 'price', 'desc']],
            'unbekannt' => [['sort' => 'stock_desc'], ['name_asc', 'name', 'asc']],
            'Spaltenname direkt' => [['sort' => 'price'], ['name_asc', 'name', 'asc']],
        ];
    }

    /**
     * @param  array<string, string>  $query
     * @param  array{string, string, string}  $expected
     */
    #[DataProvider('sorts')]
    public function test_sort_key_resolves_to_column_and_direction(array $query, array $expected): void
    {
        $this->assertSame($expected, ProductSort::fromRequest(Request::create('/products', 'GET', $query)));
    }
}
