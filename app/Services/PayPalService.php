<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Support\PaymentMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\StreamInterface;
use Srmklive\PayPal\Services\PayPal;

class PayPalService
{
    private ?PayPal $client = null;

    public function __construct(
        private readonly PayPalClientFactory $clientFactory,
    ) {}

    /**
     * Get or create the authenticated PayPal API client (lazy init).
     */
    private function client(): PayPal
    {
        if ($this->client === null) {
            $this->client = $this->clientFactory->make();
            $this->authenticate();
        }

        return $this->client;
    }

    /**
     * Obtain an OAuth 2.0 access token from PayPal.
     *
     * The SDK stores the token internally and sets the Authorization header
     * for all subsequent API calls on this client instance.
     *
     * @throws \Throwable
     */
    private function authenticate(): void
    {
        $token = $this->asArray($this->client->getAccessToken());

        if (! isset($token['access_token'])) {
            throw new \RuntimeException('PayPal authentication failed: '.($token['error']['message'] ?? 'No access token returned'));
        }

        $this->client->setAccessToken($token);
    }

    /**
     * Create a PayPal Order for the given amount.
     *
     * @param  float  $amount  Total amount in EUR (or configured currency)
     * @param  string  $currency  ISO 4217 currency code (default: EUR)
     * @param  array<string, string|null>|null  $address  Shipping address (from cart)
     * @return array<string, mixed> PayPal-Order; bei Fehlern ohne "id", dafür mit "error"
     *
     * @throws \Throwable
     */
    public function createOrder(float $amount, string $currency = 'EUR', ?array $address = null): array
    {
        $this->client()->setCurrency($currency);

        $purchaseUnit = [
            'amount' => [
                'currency_code' => $currency,
                'value' => number_format($amount, 2, '.', ''),
            ],
        ];

        // Add shipping address to purchase unit if available
        if ($address && ! empty($address['first_name']) && ! empty($address['last_name'])) {
            $purchaseUnit['shipping'] = [
                'name' => ['full_name' => trim($address['first_name'].' '.$address['last_name'])],
                'address' => [
                    'address_line_1' => trim(($address['street'] ?? '').' '.($address['house_number'] ?? '')),
                    'address_line_2' => $address['address_line2'] ?? '',
                    'admin_area_2' => $address['city'] ?? '',
                    'postal_code' => $address['zip'] ?? '',
                    'country_code' => $address['country'] ?? 'DE',
                ],
            ];
        }

        $data = [
            'intent' => 'CAPTURE',
            'purchase_units' => [$purchaseUnit],
            'application_context' => [
                'brand_name' => 'dormed 24',
                'locale' => 'de-DE',
                'landing_page' => 'NO_PREFERENCE',
                'user_action' => 'PAY_NOW',
                'return_url' => route('paypal.after-payment'),
                'cancel_url' => route('checkout.index'),
            ],
        ];

        $response = $this->asArray($this->client()->createOrder($data));

        $this->logResponse('createOrder', $response);

        return $response;
    }

    /**
     * Capture an approved PayPal Order.
     *
     * @param  string  $paypalOrderId  The PayPal Order ID (e.g., "1AB23456CD7890")
     * @return array<string, mixed>
     *
     * @throws \Throwable
     */
    public function captureOrder(string $paypalOrderId): array
    {
        $response = $this->asArray($this->client()->capturePaymentOrder($paypalOrderId));

        $this->logResponse('captureOrder', $response);

        return $response;
    }

    /**
     * Refund a captured payment.
     *
     * @param  string  $captureId  The PayPal Capture/Transaction ID
     * @param  float  $amount  Amount to refund
     * @param  string  $note  Reason for refund
     * @return array<string, mixed>
     *
     * @throws \Throwable
     */
    public function refundOrder(string $captureId, float $amount, string $note = 'Rückerstattung'): array
    {
        $invoiceId = 'REFUND-'.str()->random(12);

        $response = $this->asArray($this->client()->refundCapturedPayment(
            $captureId,
            $invoiceId,
            $amount,
            $note
        ));

        $this->logResponse('refundOrder', $response);

        return $response;
    }

    /**
     * Verify an incoming PayPal webhook notification.
     *
     * @param  Request  $request  The incoming HTTP request
     * @return bool Whether the webhook is authentic
     */
    public function verifyWebhook(Request $request): bool
    {
        $webhookId = config('paypal.'.PaymentMode::current().'.webhook_id');

        if (blank($webhookId)) {
            Log::warning('PayPal webhook verification skipped: no webhook ID configured');

            return false;
        }

        $headers = [
            'PAYPAL-AUTH-ALGO' => (string) $request->header('PAYPAL-AUTH-ALGO'),
            'PAYPAL-CERT-URL' => (string) $request->header('PAYPAL-CERT-URL'),
            'PAYPAL-TRANSMISSION-ID' => (string) $request->header('PAYPAL-TRANSMISSION-ID'),
            'PAYPAL-TRANSMISSION-SIG' => (string) $request->header('PAYPAL-TRANSMISSION-SIG'),
            'PAYPAL-TRANSMISSION-TIME' => (string) $request->header('PAYPAL-TRANSMISSION-TIME'),
        ];

        // Lokale Prüfung mit PayPals Zertifikat: braucht weder Access-Token
        // noch API-Aufruf und arbeitet auf dem unveränderten Body (CRC32).
        // Das Zertifikat lädt das SDK nur von PayPal-Domains.
        try {
            $verified = $this->clientFactory->make()
                ->verifyWebHookLocally($headers, (string) $webhookId, $request->getContent());

            if (! $verified) {
                Log::warning('PayPal webhook signature invalid', [
                    'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'],
                ]);
            }

            return $verified;
        } catch (\Throwable $e) {
            Log::error('PayPal webhook verification failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Extract the capture ID from a captured order response.
     *
     * Reiner Array-Zugriff wie im SDK-Helper, aber ohne den Umweg über
     * client(): der hätte nur dafür ein Access-Token bei PayPal geholt.
     *
     * @param  array<string, mixed>  $orderResponse
     */
    public function getCaptureIdFromOrder(array $orderResponse): ?string
    {
        $captureId = $orderResponse['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;

        return is_string($captureId) ? $captureId : null;
    }

    /**
     * Persist a payment record from PayPal response data.
     *
     * @param  array<string, mixed>  $response
     */
    public function recordPayment(Order $order, array $response, string $status): Payment
    {
        $purchaseUnit = $response['purchase_units'][0] ?? [];
        $capture = $purchaseUnit['payments']['captures'][0] ?? [];
        $payer = $response['payer'] ?? [];

        return Payment::query()->create([
            'order_id' => $order->id,
            'paypal_order_id' => $response['id'] ?? null,
            'paypal_payer_id' => $payer['payer_id'] ?? null,
            'paypal_capture_id' => $capture['id'] ?? null,
            'status' => $status,
            // Die createOrder-Antwort enthält nur id/status/links: dann gilt der
            // angefragte Bestellbetrag, bis completeCapture() den eingezogenen
            // Betrag einträgt.
            'amount' => $capture['amount']['value'] ?? $purchaseUnit['amount']['value'] ?? $order->total_amount,
            'currency' => $capture['amount']['currency_code'] ?? $purchaseUnit['amount']['currency_code'] ?? 'EUR',
            'fee' => $capture['seller_receivable_breakdown']['paypal_fee']['value'] ?? null,
            'payer_email' => $payer['email_address'] ?? null,
            'payer_name' => ($payer['name']['given_name'] ?? '').' '.($payer['name']['surname'] ?? ''),
            'response_data' => $response,
        ]);
    }

    /**
     * Log API responses for debugging.
     *
     * @param  array<string, mixed>  $response
     */
    private function logResponse(string $method, array $response): void
    {
        Log::debug("PayPal API ({$method}) response", [
            'response' => $response,
        ]);
    }

    /**
     * Das SDK deklariert seine Antworten als array|StreamInterface|string.
     * Dekodiertes JSON ist praktisch immer ein Array, API-Fehler kommen als
     * ['error' => …]. Bleibt ausnahmsweise ein String oder Stream übrig
     * (Nicht-JSON-Antwort), wird er in dieselbe Fehlerform gebracht, statt
     * später an einem array-Rückgabetyp zu scheitern.
     *
     * @param  array<string, mixed>|StreamInterface|string  $response
     * @return array<string, mixed>
     */
    private function asArray(array|StreamInterface|string $response): array
    {
        if (is_array($response)) {
            return $response;
        }

        return ['error' => (string) $response];
    }
}
