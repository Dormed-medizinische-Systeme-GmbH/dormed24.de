<?php

namespace Tests\Feature\Checkout;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Läuft bewusst gegen den ECHTEN PayPalService (kein Mock): die Signatur-
 * Prüfung ist die einzige Authentifizierung des Webhook-Endpunkts.
 */
class PayPalWebhookVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{bool}>
     */
    public static function operatingModes(): array
    {
        return [
            'sandbox' => [false],
            'live' => [true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('operatingModes')]
    public function test_webhook_is_rejected_when_no_webhook_id_is_configured(bool $live): void
    {
        Mail::fake();

        if ($live) {
            $this->app->detectEnvironment(fn () => 'production');
            config(['app.debug' => false]);
        }

        config([
            'paypal.sandbox.webhook_id' => '',
            'paypal.live.webhook_id' => '',
        ]);

        $order = Order::factory()->create(['status' => 'pending', 'payment_method' => 'paypal']);
        $payment = Payment::factory()->for($order)->create([
            'status' => 'APPROVED',
            'paypal_capture_id' => 'CAPTURE-UNSIGNED',
            'amount' => $order->total_amount,
        ]);

        $this->postJson(route('paypal.webhook'), [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE-UNSIGNED',
                'amount' => ['value' => (string) $order->total_amount],
            ],
        ])->assertStatus(400);

        $this->assertSame('APPROVED', $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->status);
        Mail::assertNothingSent();
    }
}
