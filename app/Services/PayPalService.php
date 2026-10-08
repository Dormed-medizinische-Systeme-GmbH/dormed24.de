<?php

namespace App\Services;

use App\Support\PaymentMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\StreamInterface;
use Srmklive\PayPal\Services\PayPal;

/**
 * Spricht mit der PayPal-REST-API über das SDK und gibt die Antworten roh
 * zurück. Übersetzt werden sie in PayPalGateway.
 */
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
                'address' => array_filter([
                    'address_line_1' => trim(($address['street'] ?? '').' '.($address['house_number'] ?? '')),
                    // Leere Felder weglassen statt "" zu schicken.
                    'address_line_2' => trim((string) ($address['address_line2'] ?? '')),
                    'admin_area_2' => $address['city'] ?? '',
                    'postal_code' => $address['zip'] ?? '',
                    'country_code' => $address['country'] ?? 'DE',
                ], fn (?string $value): bool => $value !== null && $value !== ''),
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
                // Lieferadresse kommt aus dem Checkout; der Käufer soll sie bei
                // PayPal nicht ändern, sonst weicht sie von der Bestellung ab.
                'shipping_preference' => isset($purchaseUnit['shipping']) ? 'SET_PROVIDED_ADDRESS' : 'NO_SHIPPING',
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
     * @param  string  $currency  Currency of the capture
     * @param  string  $note  Reason for refund
     * @return array<string, mixed>
     *
     * @throws \Throwable
     */
    public function refundOrder(string $captureId, float $amount, string $currency = 'EUR', string $note = 'Rückerstattung'): array
    {
        $invoiceId = 'REFUND-'.str()->random(12);

        // Gleicher Schlüssel für dieselbe Erstattung: Ein Doppelklick oder ein
        // Retry nach Timeout führt bei PayPal nicht zu einer zweiten Erstattung.
        $response = $this->asArray($this->client()
            ->setCurrency($currency)
            ->withIdempotencyKey('refund-'.$captureId.'-'.number_format($amount, 2, '.', ''))
            ->refundCapturedPayment(
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
