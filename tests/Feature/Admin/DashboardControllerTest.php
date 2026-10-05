<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_revenue_only_counts_paid_orders(): void
    {
        // is_test no longer excludes an order from revenue: PaymentMode (and
        // therefore is_test) is derived exclusively from APP_ENV, so a
        // sandbox order structurally cannot occur in production - the
        // filter existed for a scenario (admin-togglable sandbox in prod)
        // that no longer exists.
        Order::factory()->create(['status' => 'paid', 'total_amount' => '100.00', 'is_test' => false]);
        Order::factory()->create(['status' => 'paid', 'total_amount' => '50.00', 'is_test' => false]);
        Order::factory()->create(['status' => 'paid', 'total_amount' => '9999.00', 'is_test' => true]);

        Order::factory()->create(['status' => 'pending', 'total_amount' => '500.00', 'is_test' => false]);
        Order::factory()->create(['status' => 'failed', 'total_amount' => '500.00', 'is_test' => false]);
        Order::factory()->create(['status' => 'cancelled', 'total_amount' => '500.00', 'is_test' => false]);

        $today = now()->toDateString();

        $response = $this->actingAsAdmin()->get(route('admin.dashboard'));
        $response->assertOk();

        $chartData = collect($response->viewData('page')['props']['chartData']);
        $todayRow = $chartData->firstWhere('date', $today);

        $this->assertSame(3, $todayRow['orders']);
        $this->assertSame(10149.0, $todayRow['revenue']);
        $this->assertCount(730, $chartData);
    }
}
