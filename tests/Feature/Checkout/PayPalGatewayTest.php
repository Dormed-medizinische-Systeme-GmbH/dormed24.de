<?php

namespace Tests\Feature\Checkout;

use App\Services\PayPalGateway;
use App\Services\PayPalService;
use App\Support\Orders\Gateway\WebhookEventType;
use App\Support\Orders\PaymentStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\PayPalFixtures;
use Tests\TestCase;

/**
 * Der PayPal-Adapter übersetzt die spezifikationsgeprüften PayPal-Antworten
 * in neutrale Ergebnisse. Hier steht jeder PayPal-JSON-Pfad unter Test.
 */
class PayPalGatewayTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $response
     */
    private function gatewayReturning(string $method, array $response): PayPalGateway
    {
        $this->mock(PayPalService::class, fn ($mock) => $mock->shouldReceive($method)->once()->andReturn($response));

        return app(PayPalGateway::class);
    }

    public function test_created_order_yields_its_reference(): void
    {
        $created = $this->gatewayReturning('createOrder', PayPalFixtures::sdkResponse('orders/create'))->createPayment(439000, 'EUR', []);

        $this->assertTrue($created->succeeded());
        $this->assertSame('5R197099C6919743A', $created->orderReference);
        $this->assertSame('CREATED', $created->status);
    }

    public function test_rejected_order_yields_no_reference(): void
    {
        $created = $this->gatewayReturning('createOrder', PayPalFixtures::sdkResponse('orders/create-unprocessable'))->createPayment(1000, 'EUR', []);

        $this->assertFalse($created->succeeded());
    }

    public function test_amount_is_sent_in_euros(): void
    {
        $this->mock(PayPalService::class, fn ($mock) => $mock->shouldReceive('createOrder')
            ->once()
            ->with(4390.0, 'EUR', ['city' => 'Berlin'])
            ->andReturn(PayPalFixtures::sdkResponse('orders/create')));

        app(PayPalGateway::class)->createPayment(439000, 'EUR', ['city' => 'Berlin']);
    }

    public function test_completed_capture_carries_amount_fee_and_payer(): void
    {
        $result = $this->gatewayReturning('captureOrder', PayPalFixtures::sdkResponse('orders/capture-completed'))->capture('5R197099C6919743A');

        $this->assertSame(PaymentStatus::Completed, $result->status);
        $this->assertSame('5XV36579Y29910400', $result->captureId);
        $this->assertSame('4390.00', $result->amount);
        $this->assertSame('EUR', $result->currency);
        $this->assertSame('153.99', $result->fee);
        $this->assertSame('2TNVE2PULBM66', $result->payerId);
        $this->assertSame('sb-3xdck39855217@personal.example.com', $result->payerEmail);
        $this->assertSame('John Doe', $result->payerName);
        $this->assertSame(PayPalFixtures::sdkResponse('orders/capture-completed'), $result->raw);
    }

    /**
     * Die Order steht in allen drei Fixtures auf COMPLETED; maßgeblich ist
     * der Status der Capture.
     *
     * @return array<string, array{string, PaymentStatus}>
     */
    public static function captureStatuses(): array
    {
        return [
            'completed' => ['orders/capture-completed', PaymentStatus::Completed],
            'pending' => ['orders/capture-pending', PaymentStatus::Pending],
            'declined' => ['orders/capture-declined', PaymentStatus::Failed],
            'instrument declined' => ['orders/capture-instrument-declined', PaymentStatus::Failed],
        ];
    }

    #[DataProvider('captureStatuses')]
    public function test_capture_status_comes_from_the_capture_not_the_order(string $fixture, PaymentStatus $expected): void
    {
        $result = $this->gatewayReturning('captureOrder', PayPalFixtures::sdkResponse($fixture))->capture('5R197099C6919743A');

        $this->assertSame($expected, $result->status);
    }

    public function test_capture_without_valid_capture_id_has_none(): void
    {
        $result = $this->gatewayReturning('captureOrder', [
            'purchase_units' => [['payments' => ['captures' => [['id' => 123, 'status' => 'COMPLETED']]]]],
        ])->capture('ORDER-1');

        $this->assertNull($result->captureId);
    }

    /**
     * @return array<string, array{string, PaymentStatus}>
     */
    public static function refundStatuses(): array
    {
        return [
            'completed' => ['payments/refund-completed', PaymentStatus::Refunded],
            'pending' => ['payments/refund-pending', PaymentStatus::Pending],
            'failed' => ['payments/refund-failed', PaymentStatus::Failed],
            'cancelled' => ['payments/refund-cancelled', PaymentStatus::Failed],
            'rejected' => ['payments/refund-zero-amount', PaymentStatus::Failed],
        ];
    }

    #[DataProvider('refundStatuses')]
    public function test_refund_status_is_translated(string $fixture, PaymentStatus $expected): void
    {
        $result = $this->gatewayReturning('refundOrder', PayPalFixtures::sdkResponse($fixture))->refund('5XV36579Y29910400', 4390.0, 'EUR');

        $this->assertSame($expected, $result->status);
    }

    public function test_capture_completed_webhook(): void
    {
        $event = app(PayPalGateway::class)->parseWebhook(PayPalFixtures::webhookEvent('PAYMENT.CAPTURE.COMPLETED', 'webhooks/capture'));

        $this->assertSame(WebhookEventType::CaptureCompleted, $event->type);
        $this->assertSame('5XV36579Y29910400', $event->captureId);
        $this->assertSame('5R197099C6919743A', $event->orderReference);
        $this->assertSame('4390.00', $event->amount);
        $this->assertSame('EUR', $event->currency);
        $this->assertSame('153.99', $event->fee);
    }

    public function test_refund_webhook_reads_the_capture_id_from_the_up_link(): void
    {
        $event = app(PayPalGateway::class)->parseWebhook(PayPalFixtures::webhookEvent('PAYMENT.CAPTURE.REFUNDED', 'webhooks/refund'));

        $this->assertSame(WebhookEventType::CaptureRefunded, $event->type);
        $this->assertSame('5XV36579Y29910400', $event->captureId, 'Nicht die Refund-ID 1JU08902781691411.');
        $this->assertSame('4390.00', $event->amount);
    }

    public function test_reversed_webhook_reads_the_capture_id_from_the_up_link(): void
    {
        $event = app(PayPalGateway::class)->parseWebhook(PayPalFixtures::webhookEvent('PAYMENT.CAPTURE.REVERSED', 'webhooks/refund'));

        $this->assertSame(WebhookEventType::CaptureReversed, $event->type);
        $this->assertSame('5XV36579Y29910400', $event->captureId);
    }

    public function test_order_approved_webhook_carries_the_order_reference(): void
    {
        $event = app(PayPalGateway::class)->parseWebhook(PayPalFixtures::webhookEvent('CHECKOUT.ORDER.APPROVED', 'webhooks/order-approved'));

        $this->assertSame(WebhookEventType::PaymentApproved, $event->type);
        $this->assertSame('5R197099C6919743A', $event->orderReference);
    }

    public function test_pending_and_denied_webhooks(): void
    {
        $gateway = app(PayPalGateway::class);

        $pending = $gateway->parseWebhook(PayPalFixtures::webhookEvent('PAYMENT.CAPTURE.PENDING', 'webhooks/capture'));
        $denied = $gateway->parseWebhook(PayPalFixtures::webhookEvent('PAYMENT.CAPTURE.DENIED', 'webhooks/capture-denied'));

        $this->assertSame(WebhookEventType::CapturePending, $pending->type);
        $this->assertSame('5R197099C6919743A', $pending->orderReference);
        $this->assertSame(WebhookEventType::CaptureDenied, $denied->type);
        $this->assertNotNull($denied->captureId);
    }

    public function test_unknown_or_broken_events(): void
    {
        $gateway = app(PayPalGateway::class);

        $this->assertSame(WebhookEventType::Unhandled, $gateway->parseWebhook(['event_type' => 'CUSTOMER.DISPUTE.CREATED', 'resource' => ['id' => 'X']])->type);

        $broken = $gateway->parseWebhook(['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => 'kein Objekt']);
        $this->assertSame(WebhookEventType::CaptureCompleted, $broken->type);
        $this->assertNull($broken->captureId);
    }
}
