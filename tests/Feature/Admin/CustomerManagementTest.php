<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_customers(): void
    {
        Customer::factory()->count(3)->create();

        $this->actingAsAdmin()
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Customers/Index')->has('customers'));
    }

    public function test_show_renders_customer_with_orders(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->for($customer)->create();

        $this->actingAsAdmin()
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('Admin/Customers/Show')
                    ->where('customer.id', $customer->id)
                    ->has('customer.orders')
            );
    }
}
