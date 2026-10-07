<?php

namespace Tests\Feature\Checkout;

use App\Services\PayPalClientFactory;
use App\Services\PayPalService;
use Srmklive\PayPal\Services\PayPal;
use Tests\Support\PayPalFixtures;
use Tests\TestCase;

/**
 * PayPalService gegen einen präparierten SDK-Client: kein Netzwerk, aber
 * die echte Normalisierung der SDK-Antworten.
 */
class PayPalServiceResponseTest extends TestCase
{
    /**
     * @var array<string, mixed>|string
     */
    private static array|string $captureResponse = [];

    protected function setUp(): void
    {
        parent::setUp();

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

                    public function capturePaymentOrder(string $order_id, array $data = [])
                    {
                        return PayPalServiceResponseTest::captureResponse();
                    }
                };
            }
        });
    }

    /**
     * @return array<string, mixed>|string
     */
    public static function captureResponse(): array|string
    {
        return self::$captureResponse;
    }

    public function test_array_response_is_returned_unchanged(): void
    {
        self::$captureResponse = PayPalFixtures::body('orders/capture-completed');

        $this->assertSame(
            PayPalFixtures::body('orders/capture-completed'),
            app(PayPalService::class)->captureOrder('5R197099C6919743A'),
        );
    }

    public function test_non_json_response_becomes_error_array_instead_of_type_error(): void
    {
        self::$captureResponse = 'Bad Gateway';

        $this->assertSame(
            ['error' => 'Bad Gateway'],
            app(PayPalService::class)->captureOrder('ORDER-1'),
        );
    }

    public function test_capture_id_is_read_from_order_response(): void
    {
        $service = app(PayPalService::class);

        $this->assertSame('5XV36579Y29910400', $service->getCaptureIdFromOrder(PayPalFixtures::body('orders/capture-completed')));
        $this->assertNull($service->getCaptureIdFromOrder(PayPalFixtures::body('orders/create')));
        $this->assertNull($service->getCaptureIdFromOrder([
            'purchase_units' => [['payments' => ['captures' => [['id' => 123]]]]],
        ]));
    }
}
