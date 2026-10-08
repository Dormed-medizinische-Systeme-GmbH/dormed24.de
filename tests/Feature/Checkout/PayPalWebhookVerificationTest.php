<?php

namespace Tests\Feature\Checkout;

use App\Models\Order;
use App\Models\Payment;
use App\Services\PayPalClientFactory;
use App\Support\Orders\OrderStatus;
use App\Support\Orders\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Srmklive\PayPal\Services\PayPal;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\PayPalFixtures;
use Tests\TestCase;

/**
 * Läuft bewusst gegen den ECHTEN PayPalService (kein Mock): die Signatur-
 * Prüfung ist die einzige Authentifizierung des Webhook-Endpunkts.
 */
class PayPalWebhookVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_ID = 'WH-TEST-123';

    private const CERT_URL = 'https://api.sandbox.paypal.com/v1/notifications/certs/CERT-TEST';

    /**
     * Schlüssel und Zertifikat unter tests/Fixtures/paypal-webhook sind
     * reine Testdaten. Nur der Abruf des Zertifikats ist ersetzt, die
     * Signaturprüfung selbst läuft im echten SDK.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        // Das SDK verlangt beim Erzeugen Zugangsdaten, auch wenn die
        // lokale Signaturprüfung sie nicht verwendet.
        config([
            'paypal.sandbox.client_id' => 'test-client-id',
            'paypal.sandbox.client_secret' => 'test-client-secret',
            'paypal.sandbox.webhook_id' => self::WEBHOOK_ID,
        ]);

        $this->app->instance(PayPalClientFactory::class, new class extends PayPalClientFactory
        {
            protected function newClient(array $config): PayPal
            {
                return new class($config) extends PayPal
                {
                    protected function fetchCert(string $url): string
                    {
                        return (string) file_get_contents(base_path('tests/Fixtures/paypal-webhook/signing-cert.pem'));
                    }
                };
            }
        });
    }

    public function test_correctly_signed_webhook_is_accepted_and_processed(): void
    {
        [$order, $payment] = $this->paidOrder();

        $this->sendWebhook($this->refundBody($payment))->assertOk();

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
    }

    public function test_webhook_with_tampered_body_is_rejected(): void
    {
        [$order, $payment] = $this->paidOrder();
        $body = $this->refundBody($payment);

        $this->sendWebhook($body, signedBody: $body.' ')->assertStatus(400);

        $this->assertSame(PaymentStatus::Completed, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_webhook_signed_with_foreign_key_is_rejected(): void
    {
        [$order, $payment] = $this->paidOrder();

        $this->sendWebhook($this->refundBody($payment), key: 'foreign-key.pem')->assertStatus(400);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_webhook_signed_for_another_webhook_id_is_rejected(): void
    {
        [$order, $payment] = $this->paidOrder();

        $this->sendWebhook($this->refundBody($payment), webhookId: 'WH-OTHER')->assertStatus(400);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_webhook_with_non_paypal_cert_url_is_rejected(): void
    {
        [$order, $payment] = $this->paidOrder();

        $this->sendWebhook($this->refundBody($payment), certUrl: 'https://evil.example.com/cert.pem')->assertStatus(400);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_webhook_without_signature_headers_is_rejected(): void
    {
        [$order, $payment] = $this->paidOrder();

        $this->call('POST', route('paypal.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $this->refundBody($payment))
            ->assertStatus(400);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    /**
     * @return array{0: Order, 1: Payment}
     */
    private function paidOrder(): array
    {
        $order = Order::factory()->create(['status' => 'paid', 'payment_method' => 'paypal']);
        $payment = Payment::factory()->completed()->for($order)->create([
            'amount' => $order->total_amount,
        ]);

        return [$order, $payment];
    }

    private function refundBody(Payment $payment): string
    {
        return (string) json_encode(PayPalFixtures::webhookEvent('PAYMENT.CAPTURE.REFUNDED', 'webhooks/refund', [
            'amount' => ['value' => (string) $payment->amount],
            'links' => [1 => ['href' => "https://api.sandbox.paypal.com/v2/payments/captures/{$payment->paypal_capture_id}"]],
        ]));
    }

    /**
     * Signiert wie PayPal: "transmissionId|transmissionTime|webhookId|crc32(body)".
     *
     * @return TestResponse<Response>
     */
    private function sendWebhook(
        string $body,
        ?string $signedBody = null,
        string $key = 'signing-key.pem',
        string $webhookId = self::WEBHOOK_ID,
        string $certUrl = self::CERT_URL,
    ): TestResponse {
        $transmissionId = 'TRANSMISSION-1';
        $transmissionTime = '2026-10-07T08:00:00Z';
        $message = "{$transmissionId}|{$transmissionTime}|{$webhookId}|".crc32($signedBody ?? $body);

        $privateKey = openssl_pkey_get_private((string) file_get_contents(base_path("tests/Fixtures/paypal-webhook/{$key}")));
        $this->assertNotFalse($privateKey);
        openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return $this->call('POST', route('paypal.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
            'HTTP_PAYPAL_CERT_URL' => $certUrl,
            'HTTP_PAYPAL_TRANSMISSION_ID' => $transmissionId,
            'HTTP_PAYPAL_TRANSMISSION_SIG' => base64_encode($signature),
            'HTTP_PAYPAL_TRANSMISSION_TIME' => $transmissionTime,
        ], $body);
    }

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

    #[DataProvider('operatingModes')]
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

        $this->postJson(route('paypal.webhook'), PayPalFixtures::webhookEvent('PAYMENT.CAPTURE.COMPLETED', 'webhooks/capture', [
            'id' => 'CAPTURE-UNSIGNED',
            'amount' => ['value' => (string) $order->total_amount],
        ]))->assertStatus(400);

        $this->assertSame(PaymentStatus::Approved, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        Mail::assertNothingSent();
    }
}
