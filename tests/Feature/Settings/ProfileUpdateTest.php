<?php

namespace Tests\Feature\Settings;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = Customer::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = Customer::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = Customer::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = Customer::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_unverified_user_can_delete_their_account(): void
    {
        // DSGVO Art. 17: die Kontolöschung darf nicht an einer
        // unbestätigten E-Mail-Adresse scheitern (V4 S-4).
        $user = Customer::factory()->unverified()->create();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = Customer::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }

    /**
     * GoBD/§147 AO: Bestellungen inkl. Positionen und Zahlungen müssen die
     * Kontolöschung überleben — die Order wird nur entkoppelt (customer_id
     * = null), nicht gelöscht.
     */
    public function test_deleting_account_keeps_the_order_history(): void
    {
        $user = Customer::factory()->create();

        /** @var Order $order */
        $order = Order::factory()->for($user, 'customer')->create(['status' => 'paid']);
        $order->items()->create([
            'product_name' => 'Testprodukt',
            'unit_price' => '19.99',
            'quantity' => 2,
        ]);
        Payment::factory()->for($order)->create();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('home'));

        $this->assertNull($user->fresh());
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'customer_id' => null, 'status' => 'paid']);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_name' => 'Testprodukt']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id]);
    }
}
