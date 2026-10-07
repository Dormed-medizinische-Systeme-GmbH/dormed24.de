<?php

namespace Tests\Feature\Checkout;

use App\Mail\NewOrderMail;
use App\Mail\OrderConfirmationMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Services\PayPalClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Srmklive\PayPal\Services\PayPal;
use Tests\Support\PayPalFixtures;
use Tests\TestCase;

/**
 * Eine PayPal-Zahlung von Anfang bis Ende: Warenkorb → PayPal-Order anlegen →
 * bezahlen → Admin-Erstattung. Der echte PayPalService läuft gegen einen
 * SDK-Client, der die spezifikationsgeprüften Fixtures zurückgibt; kein
 * Schritt bekommt einen Betrag von einer Factory vorgegeben.
 *
 * Fängt Fehler, die nur in der Kette auffallen, z. B. den 0,00-€-Refund:
 * createOrder liefert keinen Betrag, der Capture musste ihn nachtragen.
 */
class PayPalPaymentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mit welchem Betrag die Erstattung bei PayPal angefragt wurde.
     */
    public static ?float $refundedAmount = null;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Setting::set('shop.email', 'shop@dormed.example');
        self::$refundedAmount = null;

        config([
            'paypal.sandbox.client_id' => 'test-client-id',
            'paypal.sandbox.client_secret' => 'test-client-secret',
        ]);

        $this->app->instance(PayPalClientFactory::class, new class extends PayPalClientFactory
        {
            protected function newClient(array $config): PayPal
            {
                return new class($config) extends PayPal
                {
                    public function getAccessToken()
                    {
                        return ['access_token' => 'TEST-TOKEN', 'token_type' => 'Bearer', 'expires_in' => 3600];
                    }

                    public function createOrder(array $data)
                    {
                        return PayPalFixtures::sdkResponse('orders/create');
                    }

                    public function capturePaymentOrder(string $order_id, array $data = [])
                    {
                        return PayPalFixtures::sdkResponse('orders/capture-completed');
                    }

                    public function refundCapturedPayment(string $capture_id, string $invoice_id, float $amount, string $note)
                    {
                        PayPalPaymentLifecycleTest::$refundedAmount = $amount;

                        // So reagiert PayPal auf eine Erstattung über 0,00 €.
                        return $amount > 0
                            ? PayPalFixtures::sdkResponse('payments/refund-completed')
                            : PayPalFixtures::sdkResponse('payments/refund-zero-amount');
                    }
                };
            }
        });
    }

    public function test_paypal_payment_can_be_created_captured_and_refunded(): void
    {
        $customer = Customer::factory()->create();
        // Die Capture-Fixture hat 4.390,00 € eingezogen.
        $product = Product::factory()->create(['price' => '4390.00']);

        $this->actingAs($customer)
            ->withSession(['cart' => [
                'items' => [(string) $product->id => 1],
                'shipping_address' => [
                    'first_name' => 'Max',
                    'last_name' => 'Mustermann',
                    'street' => 'Musterstraße',
                    'house_number' => '12',
                    'zip' => '10115',
                    'city' => 'Berlin',
                    'country' => 'DE',
                ],
            ]])
            ->postJson(route('paypal.order.create'), ['agreed_to_terms' => true])
            ->assertOk()
            ->assertJson(['id' => '5R197099C6919743A', 'status' => 'CREATED']);

        /** @var Order $order */
        $order = Order::query()->sole();
        /** @var Payment $payment */
        $payment = $order->payments()->sole();
        $this->assertSame('4390.00', $order->total_amount);
        $this->assertSame('CREATED', $payment->status);
        $this->assertEquals(4390.00, (float) $payment->amount, 'createOrder liefert keinen Betrag: der Bestellbetrag muss gelten.');

        $this->actingAs($customer)
            ->postJson(route('paypal.order.capture'), ['paypal_order_id' => '5R197099C6919743A'])
            ->assertOk()
            ->assertJson(['status' => 'COMPLETED']);

        $payment->refresh();
        $this->assertSame('COMPLETED', $payment->status);
        $this->assertSame('5XV36579Y29910400', $payment->paypal_capture_id);
        $this->assertEquals(4390.00, (float) $payment->amount);
        $this->assertEquals(153.99, (float) $payment->fee);
        $this->assertSame('paid', $order->fresh()->status);
        Mail::assertSent(OrderConfirmationMail::class, fn ($mail): bool => $mail->hasTo($customer->email));
        Mail::assertSent(NewOrderMail::class, fn ($mail): bool => $mail->hasTo('shop@dormed.example'));

        $this->actingAsAdmin()
            ->postJson(route('admin.orders.refund', $order))
            ->assertOk()
            ->assertJson(['status' => 'refunded']);

        $this->assertSame(4390.0, self::$refundedAmount);
        $this->assertSame('REFUNDED', $payment->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->status);
        // Bestätigungen gehen genau einmal raus, auch über Capture und Erstattung hinweg.
        Mail::assertSent(OrderConfirmationMail::class, 1);
        Mail::assertSent(NewOrderMail::class, 1);
    }
}
