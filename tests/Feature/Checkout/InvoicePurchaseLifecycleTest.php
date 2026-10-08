<?php

namespace Tests\Feature\Checkout;

use App\Mail\NewOrderMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\PaymentConfirmationMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Support\Orders\OrderStatus;
use App\Support\Orders\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Kauf auf Rechnung von Anfang bis Ende, nur über die echten Routen:
 * Warenkorb → Versand und Zahlart → Adresse → Bestellung → Zahlungseingang
 * im Admin. Prüft Beträge und welche Mail an wen geht.
 */
class InvoicePurchaseLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_order_can_be_placed_and_marked_paid(): void
    {
        Mail::fake();
        config(['shop.contact.email' => 'shop@dormed.example']);

        $customer = Customer::factory()->create(['email' => 'praxis@example.com']);
        $product = Product::factory()->create(['price' => '595.00']);
        $shipping = ShippingMethod::factory()->create(['price' => '5.90']);

        $this->actingAs($customer)
            ->post(route('cart.items.store'), ['product_id' => $product->id, 'quantity' => 2])
            ->assertSessionHasNoErrors();
        $this->patch(route('cart.shipping.update'), ['shipping_method' => (string) $shipping->id])
            ->assertSessionHasNoErrors();
        $this->patch(route('checkout.payment.update'), ['payment_method' => 'invoice'])
            ->assertSessionHasNoErrors();
        $this->patch(route('checkout.address.update'), ['shipping_address' => [
            'first_name' => 'Erika',
            'last_name' => 'Musterfrau',
            'street' => 'Musterstraße',
            'house_number' => '12',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
        ]])->assertSessionHasNoErrors();

        $response = $this->post(route('checkout.submit'), ['agreed_to_terms' => true]);

        /** @var Order $order */
        $order = Order::query()->sole();
        $response->assertRedirect(route('checkout.success', ['order_id' => $order->id]));
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentMethod::Invoice, $order->payment_method);
        $this->assertSame('1195.90', $order->total_amount);
        $this->assertSame('5.90', $order->shipping_amount);
        $this->assertSame(2, $order->items()->sole()->quantity);
        $this->assertSame('Berlin', $order->shipping_address['city']);
        $this->assertSame([], session('cart.items', []), 'Der Warenkorb ist nach der Bestellung leer.');

        Mail::assertSent(OrderConfirmationMail::class, fn ($mail): bool => $mail->hasTo('praxis@example.com'));
        Mail::assertSent(NewOrderMail::class, fn ($mail): bool => $mail->hasTo('shop@dormed.example'));
        Mail::assertNotSent(PaymentConfirmationMail::class);

        $this->get(route('checkout.success', ['order_id' => $order->id]))->assertOk();

        $this->actingAsAdmin()
            ->patchJson(route('admin.orders.confirm-payment', $order), ['notify' => true])
            ->assertOk()
            ->assertJson(['status' => 'paid']);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        Mail::assertSent(PaymentConfirmationMail::class, fn ($mail): bool => $mail->hasTo('praxis@example.com'));
        Mail::assertSent(OrderConfirmationMail::class, 1);
        Mail::assertSent(NewOrderMail::class, 1);
    }
}
