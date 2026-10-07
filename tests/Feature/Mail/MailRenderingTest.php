<?php

namespace Tests\Feature\Mail;

use App\Mail\PaymentAmountMismatchMail;
use App\Mail\PaymentConfirmationMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Die übrigen Tests verschicken Mails nur gegen Mail::fake(). Hier werden die
 * Templates wirklich gerendert, damit ein kaputtes Blade nicht erst beim
 * echten Versand auffällt.
 */
class MailRenderingTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrder(): Order
    {
        $customer = Customer::factory()->create(['name' => 'Erika Musterfrau']);
        $order = Order::factory()->for($customer)->create([
            'status' => 'paid',
            'total_amount' => '1195.90',
            'shipping_amount' => '5.90',
            'shipping_address' => [
                'company' => 'Praxis Musterfrau',
                'first_name' => 'Erika',
                'last_name' => 'Musterfrau',
                'street' => 'Musterstraße',
                'house_number' => '12',
                'zip' => '10115',
                'city' => 'Berlin',
                'country' => 'DE',
            ],
        ]);
        $order->items()->create([
            'product_id' => null,
            'product_name' => 'Therapieliege Comfort',
            'unit_price' => '595.00',
            'quantity' => 2,
        ]);

        return $order;
    }

    public function test_payment_confirmation_renders_items_totals_and_address(): void
    {
        $order = $this->paidOrder();
        $mail = new PaymentConfirmationMail($order, $order->customer);

        $mail->assertHasSubject("Zahlungsbestätigung #{$order->id} — dormed24.de");
        $mail->assertSeeInHtml('Erika Musterfrau');
        $mail->assertSeeInHtml('Therapieliege Comfort');
        $mail->assertSeeInOrderInHtml(['595,00', '1.190,00']);
        $mail->assertSeeInOrderInHtml(['1.190,00 €*', '5,90 €*', '1.195,90']);
        $mail->assertSeeInHtml('Praxis Musterfrau');
        $mail->assertSeeInHtml('10115 Berlin');
    }

    public function test_payment_confirmation_renders_without_shipping_address(): void
    {
        $order = $this->paidOrder();
        $order->update(['shipping_address' => null]);

        $html = (new PaymentConfirmationMail($order, $order->customer))->render();

        $this->assertStringContainsString("Bestellung #{$order->id}", $html);
    }

    public function test_amount_mismatch_alert_renders_both_amounts_and_admin_link(): void
    {
        $order = $this->paidOrder();
        $payment = Payment::factory()->for($order)->create(['paypal_order_id' => '5R197099C6919743A']);
        $mail = new PaymentAmountMismatchMail($payment, '1000.00');

        $mail->assertHasSubject("PayPal-Betragsabweichung bei Bestellung #{$order->id} — dormed24.de");
        $mail->assertSeeInHtml('5R197099C6919743A');
        $mail->assertSeeInHtml('1.000,00');
        $mail->assertSeeInHtml('1.195,90');
        $mail->assertSeeInHtml(route('admin.orders.show', $order), false);
    }
}
