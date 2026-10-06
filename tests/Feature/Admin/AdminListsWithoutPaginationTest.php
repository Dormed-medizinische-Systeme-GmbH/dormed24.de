<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin-Tabellen zeigen immer alle Einträge — keine Pagination.
 * 25 Einträge liegen über der früheren Seitengröße von 20.
 */
class AdminListsWithoutPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_index_lists_all_products(): void
    {
        Product::factory()->count(25)->create();

        $this->actingAsAdmin()
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Products/Index')->has('products', 25));
    }

    public function test_products_index_offers_category_filter_options(): void
    {
        Product::factory()->create(['category_slug' => 'ultraschallsonden']);

        $this->actingAsAdmin()
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('products.0.category_slug', 'ultraschallsonden')
                    ->where('categories', [
                        ['slug' => 'ultraschallsysteme', 'name' => 'Ultraschallsysteme'],
                        ['slug' => 'ultraschallsonden', 'name' => 'Ultraschallsonden'],
                        ['slug' => 'verbrauchsartikel', 'name' => 'Verbrauchsartikel'],
                        ['slug' => 'zubehoer', 'name' => 'Zubehör'],
                    ])
            );
    }

    public function test_orders_index_lists_all_orders(): void
    {
        Order::factory()->count(25)->create();

        $this->actingAsAdmin()
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Orders/Index')->has('orders', 25));
    }

    public function test_manufacturers_index_lists_all_manufacturers(): void
    {
        Manufacturer::factory()->count(25)->create();

        $this->actingAsAdmin()
            ->get(route('admin.manufacturers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Manufacturers/Index')->has('manufacturers', 25));
    }

    public function test_customers_index_lists_all_customers(): void
    {
        Customer::factory()->count(25)->create();

        $this->actingAsAdmin()
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Customers/Index')->has('customers', 25));
    }

    public function test_customer_search_still_filters_the_full_list(): void
    {
        Customer::factory()->count(25)->create();
        $match = Customer::factory()->create(['email' => 'praxis-mueller@example.com']);

        $this->actingAsAdmin()
            ->get(route('admin.customers.index', ['search' => 'praxis-mueller']))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->has('customers', 1)
                    ->where('customers.0.id', $match->id)
                    ->where('filters.search', 'praxis-mueller')
            );
    }
}
