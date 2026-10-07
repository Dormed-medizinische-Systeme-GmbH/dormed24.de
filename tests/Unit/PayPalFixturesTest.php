<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\PayPalFixtures;

/**
 * Die PayPal-Fixtures selbst: alle entsprechen den OpenAPI-Spezifikationen,
 * und der Helper lehnt Daten ab, die PayPal so nicht schicken würde.
 */
class PayPalFixturesTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function fixtures(): array
    {
        $base = dirname(__DIR__).'/Fixtures/paypal/responses/';
        $fixtures = [];

        foreach (glob($base.'*/*.json') ?: [] as $file) {
            $name = substr($file, strlen($base), -strlen('.json'));
            $fixtures[$name] = [$name];
        }

        return $fixtures;
    }

    #[DataProvider('fixtures')]
    public function test_fixture_matches_the_paypal_specification(string $name): void
    {
        $this->assertNotEmpty(PayPalFixtures::body($name));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function webhookEvents(): array
    {
        return [
            'capture completed' => ['PAYMENT.CAPTURE.COMPLETED', 'webhooks/capture'],
            'capture refunded' => ['PAYMENT.CAPTURE.REFUNDED', 'webhooks/refund'],
            'capture denied' => ['PAYMENT.CAPTURE.DENIED', 'webhooks/capture-denied'],
            'order approved' => ['CHECKOUT.ORDER.APPROVED', 'webhooks/order-approved'],
        ];
    }

    #[DataProvider('webhookEvents')]
    public function test_webhook_event_matches_the_paypal_specification(string $eventType, string $resource): void
    {
        $event = PayPalFixtures::webhookEvent($eventType, $resource);

        $this->assertSame($eventType, $event['event_type']);
        $this->assertIsArray($event['resource']);
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function invalidOverrides(): array
    {
        return [
            'unbekannter Refund-Status' => ['payments/refund-completed', ['status' => 'REFUNDED']],
            'unbekannter Order-Status' => ['orders/create', ['status' => 'PAID']],
            'Betrag als Zahl statt String' => ['webhooks/capture', ['amount' => ['value' => 4390]]],
            'Währungscode zu lang' => ['webhooks/capture', ['amount' => ['currency_code' => 'EURO']]],
            'erfundenes Feld (createOrder hat keinen Betrag)' => ['orders/create', ['amount' => ['value' => '4390.00']]],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidOverrides')]
    public function test_data_paypal_would_never_send_is_rejected(string $name, array $overrides): void
    {
        $this->expectException(RuntimeException::class);

        PayPalFixtures::body($name, $overrides);
    }

    public function test_sdk_response_wraps_http_errors_like_the_sdk(): void
    {
        $response = PayPalFixtures::sdkResponse('payments/refund-zero-amount');

        $this->assertSame('CANNOT_BE_ZERO_OR_NEGATIVE', $response['error']['details'][0]['issue']);
        $this->assertSame('COMPLETED', PayPalFixtures::sdkResponse('payments/refund-completed')['status']);
    }
}
