<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Bewertungen sind an Käufe gebunden: Nur Kunden mit einer bezahlten
 * Bestellung, die das Produkt enthält, dürfen es bewerten, und zwar einmal.
 * Bewertungen sind sofort sichtbar und werden anonym angezeigt
 * ("Verifizierter Kauf").
 */
class RatingTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_RATING = [
        'stars' => 4,
        'content' => 'Sehr gutes Gerät, solide verarbeitet.',
    ];

    /**
     * @return array{0: Customer, 1: Product, 2: Order}
     */
    private function buyer(string $orderStatus = 'paid'): array
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $order = Order::factory()->for($customer)->create(['status' => $orderStatus]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => 1,
        ]);

        return [$customer, $product, $order];
    }

    public function test_guest_cannot_rate(): void
    {
        $product = Product::factory()->create();

        $this->postJson(route('ratings.store', $product), self::VALID_RATING)->assertUnauthorized();

        $this->assertDatabaseCount('ratings', 0);
    }

    public function test_customer_who_never_bought_the_product_cannot_rate(): void
    {
        [$customer] = $this->buyer();
        $otherProduct = Product::factory()->create();

        $this->actingAs($customer)
            ->postJson(route('ratings.store', $otherProduct), self::VALID_RATING)
            ->assertForbidden();

        $this->assertDatabaseCount('ratings', 0);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unpaidOrderStatuses(): array
    {
        return [
            'offen (Rechnung noch nicht bezahlt)' => ['pending'],
            'fehlgeschlagen' => ['failed'],
            'storniert' => ['cancelled'],
            'erstattet' => ['refunded'],
        ];
    }

    #[DataProvider('unpaidOrderStatuses')]
    public function test_only_paid_orders_entitle_to_a_rating(string $status): void
    {
        [$customer, $product] = $this->buyer($status);

        $this->actingAs($customer)
            ->postJson(route('ratings.store', $product), self::VALID_RATING)
            ->assertForbidden();

        $this->assertDatabaseCount('ratings', 0);
    }

    public function test_buyer_with_paid_order_can_rate_and_rating_is_linked_to_the_order(): void
    {
        [$customer, $product, $order] = $this->buyer();

        $this->actingAs($customer)
            ->postJson(route('ratings.store', $product), self::VALID_RATING)
            ->assertCreated()
            ->assertJsonPath('rating.stars', 4);

        $this->assertDatabaseHas('ratings', [
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'stars' => 4,
            'content' => 'Sehr gutes Gerät, solide verarbeitet.',
        ]);
    }

    public function test_product_can_only_be_rated_once_per_customer(): void
    {
        [$customer, $product] = $this->buyer();
        // Zweiter Kauf desselben Produkts berechtigt nicht zu einer zweiten Bewertung.
        $secondOrder = Order::factory()->for($customer)->create(['status' => 'paid']);
        $secondOrder->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => $product->price, 'quantity' => 1]);

        $this->actingAs($customer)->postJson(route('ratings.store', $product), self::VALID_RATING)->assertCreated();

        $this->actingAs($customer)
            ->postJson(route('ratings.store', $product), self::VALID_RATING)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rating');

        $this->assertDatabaseCount('ratings', 1);
    }

    public function test_rating_submission_is_validated(): void
    {
        [$customer, $product] = $this->buyer();

        $this->actingAs($customer)
            ->postJson(route('ratings.store', $product), ['stars' => 6, 'content' => 'kurz'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['stars', 'content']);

        $this->assertDatabaseCount('ratings', 0);
    }

    public function test_product_page_contains_rating_summary_and_ratings(): void
    {
        $product = Product::factory()->create();

        Rating::factory()->for($product)->create([
            'stars' => 5,
            'content' => 'Ausgezeichnetes Gerät.',
        ]);

        Rating::factory()->for($product)->create([
            'stars' => 3,
            'content' => 'In Ordnung für den Preis.',
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Products/Show')
                ->where('ratings.0.stars', 3)
                ->where('ratings.1.stars', 5)
                ->where('ratingSummary.count', 2)
                ->where('ratingSummary.average', '4,0'));
    }

    public function test_product_page_marks_purchase_bound_ratings_as_verified_and_shows_no_names(): void
    {
        [$customer, $product, $order] = $this->buyer();
        Rating::factory()->for($product)->create(['customer_id' => $customer->id, 'order_id' => $order->id]);
        // Bestand aus der Zeit vor der Kaufbindung.
        Rating::factory()->for($product)->create(['created_at' => now()->subYear()]);

        $this->get(route('products.show', $product))
            ->assertInertia(fn ($page) => $page
                ->where('ratings.0.verified', true)
                ->where('ratings.1.verified', false)
                ->missing('ratings.0.customer_id')
                ->missing('ratings.0.customer_name')
                // Auch die Produktdaten selbst tragen die Relation mit.
                ->missing('product.ratings.0.customer_id')
                ->missing('product.ratings.0.order_id'));
    }

    public function test_product_page_offers_the_rating_form_only_to_eligible_buyers(): void
    {
        [$customer, $product] = $this->buyer();
        [$pendingCustomer, $pendingProduct] = $this->buyer('pending');

        $this->get(route('products.show', $product))
            ->assertInertia(fn ($page) => $page->where('canRate', false));

        $this->actingAs($pendingCustomer)->get(route('products.show', $pendingProduct))
            ->assertInertia(fn ($page) => $page->where('canRate', false));

        $this->actingAs($customer)->get(route('products.show', $product))
            ->assertInertia(fn ($page) => $page->where('canRate', true));

        Rating::factory()->for($product)->create(['customer_id' => $customer->id]);

        $this->actingAs($customer)->get(route('products.show', $product))
            ->assertInertia(fn ($page) => $page->where('canRate', false));
    }

    public function test_order_detail_shows_rating_state_per_item(): void
    {
        [$customer, $product, $order] = $this->buyer();
        $ratedProduct = Product::factory()->create();
        $order->items()->create(['product_id' => $ratedProduct->id, 'product_name' => $ratedProduct->name, 'unit_price' => $ratedProduct->price, 'quantity' => 1]);
        Rating::factory()->for($ratedProduct)->create(['customer_id' => $customer->id, 'order_id' => $order->id]);
        // Produkt existiert nicht mehr (product_id null): nicht bewertbar.
        $order->items()->create(['product_id' => null, 'product_name' => 'Altes Produkt', 'unit_price' => '10.00', 'quantity' => 1]);

        $this->actingAs($customer)
            ->getJson(route('customer.orders.show', $order))
            ->assertOk()
            ->assertJsonPath('order.items.0.product_id', $product->id)
            ->assertJsonPath('order.items.0.rating', 'rateable')
            ->assertJsonPath('order.items.1.rating', 'rated')
            ->assertJsonPath('order.items.2.rating', null);
    }

    public function test_items_of_unpaid_orders_are_not_rateable(): void
    {
        [$customer, , $order] = $this->buyer('pending');

        $this->actingAs($customer)
            ->getJson(route('customer.orders.show', $order))
            ->assertJsonPath('order.items.0.rating', null);
    }

    public function test_admin_can_delete_a_rating(): void
    {
        $rating = Rating::factory()->create();

        $this->actingAsAdmin()
            ->delete(route('admin.ratings.destroy', $rating))
            ->assertRedirect();

        $this->assertModelMissing($rating);
    }

    public function test_customer_cannot_use_the_admin_delete_route(): void
    {
        [$customer] = $this->buyer();
        $rating = Rating::factory()->create();

        $this->actingAs($customer)->delete(route('admin.ratings.destroy', $rating));

        $this->assertModelExists($rating);
    }

    public function test_admin_product_edit_lists_the_product_ratings(): void
    {
        $product = Product::factory()->create();
        Rating::factory()->for($product)->create(['stars' => 2, 'content' => 'Kam beschädigt an.']);

        $this->actingAsAdmin()
            ->get(route('admin.products.edit', $product))
            ->assertInertia(fn ($page) => $page
                ->where('ratings.0.stars', 2)
                ->where('ratings.0.content', 'Kam beschädigt an.'));
    }
}
