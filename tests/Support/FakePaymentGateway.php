<?php

namespace Tests\Support;

use App\Support\Orders\Gateway\CaptureResult;
use App\Support\Orders\Gateway\CreatedPayment;
use App\Support\Orders\Gateway\PaymentGateway;
use App\Support\Orders\Gateway\RefundResult;
use App\Support\Orders\Gateway\WebhookEvent;
use App\Support\Orders\Gateway\WebhookEventType;
use App\Support\Orders\PaymentStatus;
use Illuminate\Http\Request;

/**
 * Zweiter Adapter neben PayPal: liefert, was der Test vorgibt, und merkt
 * sich die Aufrufe. Damit lässt sich der Zahlungsabgleich ohne PayPal-JSON
 * testen.
 *
 * Webhooks nimmt er in neutraler Form an: "type" ist der Name eines
 * WebhookEventType-Falls, die übrigen Schlüssel sind die Felder von
 * WebhookEvent.
 */
final class FakePaymentGateway implements PaymentGateway
{
    public CreatedPayment|\Throwable $createResult;

    /**
     * Ergebnis des Einzugs; eine Closure läuft beim Einzug (z. B. um einen
     * parallel abgeschlossenen Webhook zu simulieren) und liefert das Ergebnis.
     *
     * @var CaptureResult|\Throwable|\Closure(): CaptureResult
     */
    public CaptureResult|\Throwable|\Closure $captureResult;

    public RefundResult|\Throwable $refundResult;

    public bool $webhookVerified = true;

    /**
     * @var list<string>
     */
    public array $captured = [];

    /**
     * @var list<array{captureId: string, amount: float, currency: string}>
     */
    public array $refunds = [];

    public function __construct()
    {
        $this->createResult = new CreatedPayment('FAKE-ORDER-1', 'CREATED');
        $this->captureResult = new CaptureResult(PaymentStatus::Failed);
        $this->refundResult = new RefundResult(PaymentStatus::Refunded);
    }

    public function createPayment(int $amountCents, string $currency, array $shippingAddress): CreatedPayment
    {
        return $this->createResult instanceof \Throwable ? throw $this->createResult : $this->createResult;
    }

    public function capture(string $orderReference): CaptureResult
    {
        $this->captured[] = $orderReference;
        $result = $this->captureResult;

        if ($result instanceof \Closure) {
            $result = $result();
        }

        return $result instanceof \Throwable ? throw $result : $result;
    }

    public function refund(string $captureId, float $amount, string $currency): RefundResult
    {
        $this->refunds[] = ['captureId' => $captureId, 'amount' => $amount, 'currency' => $currency];

        return $this->refundResult instanceof \Throwable ? throw $this->refundResult : $this->refundResult;
    }

    public function verifyWebhook(Request $request): bool
    {
        return $this->webhookVerified;
    }

    public function parseWebhook(array $payload): WebhookEvent
    {
        $type = WebhookEventType::Unhandled;

        foreach (WebhookEventType::cases() as $case) {
            if ($case->name === ($payload['type'] ?? null)) {
                $type = $case;
            }
        }

        return new WebhookEvent(
            type: $type,
            name: $type->name,
            orderReference: $payload['orderReference'] ?? null,
            captureId: $payload['captureId'] ?? null,
            amount: $payload['amount'] ?? null,
            currency: $payload['currency'] ?? null,
            fee: $payload['fee'] ?? null,
            raw: $payload,
        );
    }
}
