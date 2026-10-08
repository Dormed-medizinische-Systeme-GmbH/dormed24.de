<?php

namespace Tests\Feature\Mail;

use App\Mail\OrderConfirmationMail;
use App\Mail\PaymentAmountMismatchMail;
use App\Mail\PaymentConfirmationMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Orders\PaymentMethod;
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

    public function test_mails_show_contact_and_bank_details_from_the_config(): void
    {
        config(['shop.bank.iban' => 'DE02 1234 5678 9012 3456 78', 'shop.bank.bic' => 'TESTDEFFXXX']);
        $order = $this->paidOrder();
        $order->update(['status' => 'pending', 'payment_method' => PaymentMethod::Invoice]);

        $mail = new OrderConfirmationMail($order->fresh() ?? $order, $order->customer);

        $mail->assertSeeInHtml('02301 / 188-600');
        $mail->assertSeeInHtml('tel:+492301188600', false);
        $mail->assertSeeInHtml('mail@dormed.de');
        $mail->assertSeeInHtml('Dormed medizinische Systeme GmbH');
        $mail->assertSeeInHtml('DE02 1234 5678 9012 3456 78');
        $mail->assertSeeInHtml('TESTDEFFXXX');

        $payment = new PaymentConfirmationMail($order, $order->customer);
        $payment->assertSeeInHtml('02301 / 188-600');
    }

    public function test_missing_bank_details_are_not_shown_as_admin_hint(): void
    {
        config(['shop.bank.iban' => '']);
        $order = $this->paidOrder();
        $order->update(['status' => 'pending', 'payment_method' => PaymentMethod::Invoice]);

        $mail = new OrderConfirmationMail($order, $order->customer);

        $mail->assertSeeInHtml('auf Anfrage');
        $mail->assertDontSeeInHtml('Einstellungen');
    }
}
