<?php

namespace App\Services;

use App\Support\Orders\Gateway\CaptureResult;
use App\Support\Orders\Gateway\CreatedPayment;
use App\Support\Orders\Gateway\PaymentGateway;
use App\Support\Orders\Gateway\RefundResult;
use App\Support\Orders\Gateway\WebhookEvent;
use App\Support\Orders\Gateway\WebhookEventType;
use App\Support\Orders\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PayPal-Adapter: PayPalService spricht mit der REST-API, diese Klasse
 * übersetzt die Antworten. Nur hier wird PayPal-JSON gelesen.
 */
class PayPalGateway implements PaymentGateway
{
    public function __construct(
        private readonly PayPalService $payPal,
    ) {}

    public function createPayment(int $amountCents, string $currency, array $shippingAddress): CreatedPayment
    {
        $response = $this->payPal->createOrder($amountCents / 100, $currency, $shippingAddress);

        if (! isset($response['id'])) {
            Log::error('PayPal createOrder failed', ['response' => $response]);
        }

        return new CreatedPayment(
            orderReference: $this->string($response['id'] ?? null),
            status: $this->string($response['status'] ?? null),
            raw: $response,
        );
    }

    public function capture(string $orderReference): CaptureResult
    {
        $response = $this->payPal->captureOrder($orderReference);

        // Maßgeblich ist der Status der Capture, nicht der der Order: PayPal
        // setzt die Order nach dem Capture auf COMPLETED, auch wenn die
        // Capture selbst noch PENDING (Prüfung) oder DECLINED ist.
        $capture = $response['purchase_units'][0]['payments']['captures'][0] ?? [];
        $payer = $response['payer'] ?? [];
        $payerName = trim(($payer['name']['given_name'] ?? '').' '.($payer['name']['surname'] ?? ''));

        return new CaptureResult(
            status: match ($capture['status'] ?? null) {
                'COMPLETED' => PaymentStatus::Completed,
                'PENDING' => PaymentStatus::Pending,
                default => PaymentStatus::Failed,
            },
            captureId: $this->string($capture['id'] ?? null),
            amount: $this->amount($capture['amount']['value'] ?? null),
            currency: $this->string($capture['amount']['currency_code'] ?? null),
            fee: $this->amount($capture['seller_receivable_breakdown']['paypal_fee']['value'] ?? null),
            payerId: $this->string($payer['payer_id'] ?? null),
            payerEmail: $this->string($payer['email_address'] ?? null),
            payerName: $payerName !== '' ? $payerName : null,
            raw: $response,
        );
    }

    public function refund(string $captureId, float $amount, string $currency): RefundResult
    {
        $response = $this->payPal->refundOrder($captureId, $amount, $currency);

        // Das SDK meldet API-Fehler als Array statt als Exception: ohne
        // Statusprüfung würde eine abgelehnte Erstattung als erfolgreich
        // gebucht.
        return new RefundResult(
            status: match ($response['status'] ?? null) {
                'COMPLETED' => PaymentStatus::Refunded,
                'PENDING' => PaymentStatus::Pending,
                default => PaymentStatus::Failed,
            },
            raw: $response,
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        return $this->payPal->verifyWebhook($request);
    }

    public function parseWebhook(array $payload): WebhookEvent
    {
        $name = $this->string($payload['event_type'] ?? null) ?? '';
        $resource = is_array($payload['resource'] ?? null) ? $payload['resource'] : [];
        $resourceId = $this->string($resource['id'] ?? null);
        $relatedOrderId = $this->string($resource['supplementary_data']['related_ids']['order_id'] ?? null);
        $amount = $this->amount($resource['amount']['value'] ?? null);

        Log::info('PayPal webhook received', [
            'event_type' => $name,
            'resource_id' => $resourceId,
        ]);

        return match ($name) {
            'CHECKOUT.ORDER.APPROVED' => new WebhookEvent(WebhookEventType::PaymentApproved, $name, orderReference: $resourceId, raw: $resource),
            'PAYMENT.CAPTURE.COMPLETED' => new WebhookEvent(
                WebhookEventType::CaptureCompleted,
                $name,
                orderReference: $relatedOrderId,
                captureId: $resourceId,
                amount: $amount,
                currency: $this->string($resource['amount']['currency_code'] ?? null),
                fee: $this->amount($resource['seller_receivable_breakdown']['paypal_fee']['value'] ?? null),
                raw: $resource,
            ),
            'PAYMENT.CAPTURE.PENDING' => new WebhookEvent(WebhookEventType::CapturePending, $name, orderReference: $relatedOrderId, captureId: $resourceId, raw: $resource),
            'PAYMENT.CAPTURE.DENIED' => new WebhookEvent(WebhookEventType::CaptureDenied, $name, captureId: $resourceId, raw: $resource),
            'PAYMENT.CAPTURE.REFUNDED' => new WebhookEvent(WebhookEventType::CaptureRefunded, $name, captureId: $this->captureIdFromRefund($resource), amount: $amount, raw: $resource),
            'PAYMENT.CAPTURE.REVERSED' => new WebhookEvent(WebhookEventType::CaptureReversed, $name, captureId: $this->captureIdFromRefund($resource), raw: $resource),
            default => new WebhookEvent(WebhookEventType::Unhandled, $name, raw: $resource),
        };
    }

    /**
     * PAYMENT.CAPTURE.REFUNDED und REVERSED liefern eine *Refund*-Resource:
     * "id" ist die Refund-ID, die Capture-ID steht nur im up-Link
     * (/v2/payments/captures/{id}).
     *
     * @param  array<string, mixed>  $resource
     */
    private function captureIdFromRefund(array $resource): ?string
    {
        foreach ($resource['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'up' && str_contains((string) ($link['href'] ?? ''), '/captures/')) {
                return basename((string) parse_url($link['href'], PHP_URL_PATH));
            }
        }

        // Falls PayPal doch einmal die Capture selbst schickt.
        return $this->string($resource['id'] ?? null);
    }

    /**
     * IDs und Status kommen als Strings; alles andere gilt als nicht vorhanden.
     */
    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Beträge kommen als String ("4390.00"), werden aber auch als Zahl angenommen.
     */
    private function amount(mixed $value): ?string
    {
        return is_int($value) || is_float($value) ? (string) $value : $this->string($value);
    }
}
