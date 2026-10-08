<?php

namespace App\Support\Orders;

use App\Mail\PaymentAmountMismatchMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Support\Cart\Money;
use App\Support\Orders\Gateway\CaptureResult;
use App\Support\Orders\Gateway\PaymentGateway;
use App\Support\Orders\Gateway\WebhookEvent;
use App\Support\Orders\Gateway\WebhookEventType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Zahlungsabgleich: gleicht ab, was der Zahlungsdienst meldet (Anlegen,
 * Capture, Erstattung, Webhooks), und setzt daraus Zahlungs- und
 * Bestellstatus. Return-URL, JS-Capture und Webhook laufen durch dieselben
 * Regeln; die Bestell-Übergänge selbst entscheidet der OrderManager.
 *
 * Der Zahlungsdienst kann Ereignisse doppelt, verspätet und unsortiert
 * liefern, deshalb ist jeder Schritt idempotent und dreht nichts zurück,
 * was schon abgeschlossen ist.
 */
class PaymentReconciliation
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly OrderManager $orderManager,
    ) {}

    /**
     * Legt die Zahlung beim Zahlungsdienst an und hält sie als CREATED fest.
     * Scheitert das, wird die Bestellung fehlgeschlagen und null geliefert;
     * sonst bliebe sie ohne Zahlung als „offen“ beim Kunden stehen.
     */
    public function start(Order $order): ?Payment
    {
        $currency = (string) config('paypal.currency', 'EUR');

        try {
            $created = $this->gateway->createPayment(Money::toCents($order->total_amount), $currency, $order->shipping_address ?? []);
        } catch (\Throwable $e) {
            Log::error('Payment creation exception', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            $created = null;
        }

        if ($created === null || ! $created->succeeded()) {
            $this->orderManager->transitionTo($order, OrderStatus::Failed);

            return null;
        }

        // Beim Anlegen ist noch nichts eingezogen: Bis zur Capture gilt der
        // Bestellbetrag.
        return Payment::query()->create([
            'order_id' => $order->id,
            'paypal_order_id' => $created->orderReference,
            'status' => PaymentStatus::Created,
            'amount' => $order->total_amount,
            'currency' => $currency,
            'response_data' => $created->raw,
        ]);
    }

    /**
     * Zieht eine freigegebene Zahlung ein (JS-Capture und Return-URL).
     *
     * - Completed: bezahlt, auch wenn Webhook oder der andere Pfad schneller war.
     * - Pending: der Zahlungsdienst prüft noch; die Bestellung bleibt offen,
     *   der Webhook entscheidet.
     * - Failed: nicht eingezogen oder Betrag/Währung weichen ab; Zahlung und
     *   Bestellung sind als fehlgeschlagen markiert.
     *
     * @return PaymentStatus Completed, Pending oder Failed
     *
     * @throws \Throwable wenn der Zahlungsdienst nicht erreichbar war und die
     *                    Zahlung auch nicht anderweitig abgeschlossen wurde
     */
    public function capture(Payment $payment): PaymentStatus
    {
        try {
            $result = $this->gateway->capture((string) $payment->paypal_order_id);
        } catch (\Throwable $e) {
            if ($this->markCaptureFailed($payment, ['error' => $e->getMessage()])) {
                return PaymentStatus::Completed;
            }

            throw $e;
        }

        $status = $this->applyCapture($payment, $result);

        if ($status === PaymentStatus::Failed && $this->markCaptureFailed($payment, $result->raw)) {
            return PaymentStatus::Completed;
        }

        return $status;
    }

    /**
     * Erstattet die eingezogene Zahlung vollständig.
     *
     * @return PaymentStatus Refunded, Pending (angestoßen, Webhook folgt) oder Failed
     */
    public function refund(Payment $payment): PaymentStatus
    {
        $context = ['order_id' => $payment->order_id, 'capture_id' => $payment->paypal_capture_id];

        try {
            $result = $this->gateway->refund((string) $payment->paypal_capture_id, $payment->capturedAmount(), $payment->currency ?: 'EUR');
        } catch (\Throwable $e) {
            Log::error('Payment refund failed', [...$context, 'error' => $e->getMessage()]);

            return PaymentStatus::Failed;
        }

        match ($result->status) {
            PaymentStatus::Refunded => $this->setStatus($payment, PaymentStatus::Refunded, OrderStatus::Refunded),
            PaymentStatus::Pending => Log::warning('Payment refund pending', $context),
            default => Log::error('Payment refund not confirmed', [...$context, 'response' => $result->raw]),
        };

        return $result->status;
    }

    /**
     * Wendet ein verifiziertes Webhook-Event an.
     */
    public function handleWebhook(WebhookEvent $event): void
    {
        match ($event->type) {
            WebhookEventType::PaymentApproved => $this->paymentApproved($event),
            WebhookEventType::CaptureCompleted => $this->captureCompleted($event),
            WebhookEventType::CapturePending => $this->capturePending($event),
            WebhookEventType::CaptureDenied => $this->captureDenied($event),
            WebhookEventType::CaptureRefunded => $this->captureRefunded($event),
            WebhookEventType::CaptureReversed => $this->captureReversed($event),
            WebhookEventType::Unhandled => Log::info('Payment webhook: unhandled event type', ['event_type' => $event->name]),
        };
    }

    /**
     * @return PaymentStatus Completed, Pending oder Failed
     */
    private function applyCapture(Payment $payment, CaptureResult $result): PaymentStatus
    {
        if ($result->status === PaymentStatus::Completed && ! $this->matchesOrder($payment, $result->amount, $result->currency)) {
            Log::error('Payment capture amount mismatch', [
                'paypal_order_id' => $payment->paypal_order_id,
                'captured' => $result->amount,
                'currency' => $result->currency,
                'expected' => $payment->order->total_amount,
            ]);

            $this->recordMismatch($payment, $result->captureId, $result->amount, $result->currency, $result->fee);

            return PaymentStatus::Failed;
        }

        if ($result->status === PaymentStatus::Pending) {
            Payment::query()
                ->whereKey($payment->getKey())
                ->whereIn('status', [PaymentStatus::Created, PaymentStatus::Approved, PaymentStatus::Failed])
                ->update([
                    'status' => PaymentStatus::Pending,
                    'paypal_capture_id' => $result->captureId,
                    'response_data' => $result->raw,
                ]);

            return PaymentStatus::Pending;
        }

        if ($result->status !== PaymentStatus::Completed) {
            return PaymentStatus::Failed;
        }

        $payment->update([
            'status' => PaymentStatus::Completed,
            'paypal_capture_id' => $result->captureId,
            // Eingezogener Betrag, Währung und Gebühr stehen erst in der
            // Capture; die Admin-Erstattung erstattet genau diesen Betrag.
            'amount' => $result->amount ?? $payment->amount,
            'currency' => $result->currency ?? $payment->currency,
            'fee' => $result->fee ?? $payment->fee,
            'paypal_payer_id' => $result->payerId ?? $payment->paypal_payer_id,
            'payer_email' => $result->payerEmail ?? $payment->payer_email,
            'payer_name' => $result->payerName ?? $payment->payer_name,
            'response_data' => $result->raw,
        ]);

        $this->orderManager->markPaid($payment->order);

        return PaymentStatus::Completed;
    }

    /**
     * Betrag und Währung der Capture gegen die Bestellung. Fehlt eins davon
     * in der Meldung, gilt es als passend; fehlt die Währung der Zahlung,
     * gilt die konfigurierte.
     */
    private function matchesOrder(Payment $payment, ?string $amount, ?string $currency): bool
    {
        $amountMatches = $amount === null
            || Money::toCents($amount) === Money::toCents($payment->order->total_amount);
        $currencyMatches = $currency === null
            || strtoupper($currency) === strtoupper($payment->currency ?: (string) config('paypal.currency', 'EUR'));

        return $amountMatches && $currencyMatches;
    }

    /**
     * Eingezogen, aber Betrag oder Währung passen nicht zur Bestellung: Das
     * Geld liegt beim Zahlungsdienst, die Bestellung wird nicht erfüllt. Die
     * Capture wird mit Betrag gespeichert (MISMATCH), damit der Admin sie
     * erstatten kann, und der Betreiber bekommt eine Mail.
     */
    private function recordMismatch(Payment $payment, ?string $captureId, ?string $amount, ?string $currency, ?string $fee): void
    {
        DB::transaction(function () use ($payment, $captureId, $amount, $currency, $fee): void {
            Payment::query()
                ->whereKey($payment->getKey())
                ->whereNotIn('status', [PaymentStatus::Completed, PaymentStatus::Refunded])
                ->update([
                    'status' => PaymentStatus::Mismatch,
                    'paypal_capture_id' => $captureId,
                    'amount' => $amount ?? $payment->amount,
                    'currency' => $currency ?? $payment->currency,
                    'fee' => $fee ?? $payment->fee,
                ]);

            $this->orderManager->transitionTo($payment->order, OrderStatus::Failed);
        });

        $this->notifyMismatch($payment, (string) $amount);
    }

    /**
     * Markiert einen gescheiterten Einzug, ohne eine Zahlung zu überschreiben,
     * die der andere Pfad oder der Webhook inzwischen abgeschlossen hat (der
     * verlorene Capture scheitert beim Zahlungsdienst dann als „bereits
     * eingezogen“). Liefert true, wenn die Zahlung tatsächlich COMPLETED ist.
     *
     * @param  array<string, mixed>  $responseData
     */
    private function markCaptureFailed(Payment $payment, array $responseData): bool
    {
        return DB::transaction(function () use ($payment, $responseData): bool {
            /** @var Payment $current */
            $current = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($current->status === PaymentStatus::Completed) {
                return true;
            }

            // Eingezogene Capture mit Abweichung bleibt erstattbar.
            if ($current->status !== PaymentStatus::Mismatch) {
                $current->update(['status' => PaymentStatus::Failed, 'response_data' => $responseData]);
            }

            $this->orderManager->transitionTo($payment->order, OrderStatus::Failed);

            return false;
        });
    }

    private function notifyMismatch(Payment $payment, string $capturedAmount): void
    {
        $recipient = Setting::get('shop.email');

        if (! $recipient) {
            return;
        }

        try {
            Mail::to($recipient)->send(new PaymentAmountMismatchMail($payment, $capturedAmount));
        } catch (\Throwable $e) {
            Log::channel('mail')->error('Amount mismatch alert mail failed', [
                'order_id' => $payment->order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function paymentApproved(WebhookEvent $event): void
    {
        if (! $event->orderReference) {
            return;
        }

        Payment::query()
            ->where('paypal_order_id', $event->orderReference)
            ->where('status', PaymentStatus::Created)
            ->update(['status' => PaymentStatus::Approved]);
    }

    private function captureCompleted(WebhookEvent $event): void
    {
        if (! $event->captureId) {
            return;
        }

        // Zuerst über die Capture-ID; ist die noch nicht gespeichert (Webhook
        // schneller als die Capture-Antwort), über die Order-Referenz.
        $payment = $this->paymentByCaptureId($event->captureId)
            ?? ($event->orderReference ? Payment::query()->where('paypal_order_id', $event->orderReference)->with('order')->first() : null);

        if (! $payment) {
            return;
        }

        // Eine Erstattung ist endgültig: ein verspätetes oder erneut
        // zugestelltes CAPTURE.COMPLETED dreht sie nicht zurück.
        if ($payment->status === PaymentStatus::Refunded) {
            Log::info('Payment webhook: ignoring late capture for refunded payment', [
                'capture_id' => $event->captureId,
                'payment_id' => $payment->id,
            ]);

            return;
        }

        // Betragsabgleich wie im Capture- und Return-Pfad, auch wenn die
        // Signatur verifiziert ist.
        if (! $this->matchesOrder($payment, $event->amount, $event->currency)) {
            Log::error('Payment webhook capture amount mismatch', [
                'capture_id' => $event->captureId,
                'captured' => $event->amount,
                'currency' => $event->currency,
                'expected' => $payment->order->total_amount,
            ]);

            if ($payment->status !== PaymentStatus::Completed) {
                $this->recordMismatch($payment, $event->captureId, $event->amount, $event->currency, $event->fee);
            } else {
                $this->notifyMismatch($payment, (string) $event->amount);
            }

            return;
        }

        // Bereits abgeschlossen (Normalfall: der JS-Capture war schneller):
        // response_data nicht mit der Capture-Resource überschreiben, die
        // volle Capture-Antwort ist der bessere Beleg.
        if ($payment->status !== PaymentStatus::Completed) {
            $payment->update([
                'status' => PaymentStatus::Completed,
                'paypal_capture_id' => $event->captureId,
                'amount' => $event->amount ?? $payment->amount,
                'currency' => $event->currency ?? $payment->currency,
                'fee' => $event->fee ?? $payment->fee,
                'response_data' => $event->raw,
            ]);
        }

        $this->orderManager->markPaid($payment->order);
    }

    /**
     * Der Zahlungsdienst prüft die Zahlung noch. Die Bestellung bleibt offen;
     * COMPLETED oder DENIED folgen per Webhook.
     */
    private function capturePending(WebhookEvent $event): void
    {
        if (! $event->captureId || ! $event->orderReference) {
            return;
        }

        Payment::query()
            ->where('paypal_order_id', $event->orderReference)
            ->whereIn('status', [PaymentStatus::Created, PaymentStatus::Approved, PaymentStatus::Failed])
            ->update(['status' => PaymentStatus::Pending, 'paypal_capture_id' => $event->captureId]);
    }

    private function captureDenied(WebhookEvent $event): void
    {
        if (! $event->captureId) {
            return;
        }

        $order = $this->paymentByCaptureId($event->captureId)?->order;

        // Ein spätes DENIED dreht eine bezahlte oder erstattete Bestellung
        // nicht auf fehlgeschlagen zurück.
        if ($order !== null && $order->status->isSettled()) {
            Log::info('Payment webhook: ignoring late denial for settled order', [
                'capture_id' => $event->captureId,
                'order_id' => $order->id,
            ]);

            return;
        }

        $this->setStatusByCaptureId($event->captureId, PaymentStatus::Failed, OrderStatus::Failed);
    }

    /**
     * Erstattung, z. B. direkt im Dashboard des Zahlungsdienstes ausgelöst.
     * Teilerstattungen erledigen die Zahlung nicht: Zahlung und Bestellung
     * bleiben bezahlt, der Betreiber sieht den Vorgang im Log.
     */
    private function captureRefunded(WebhookEvent $event): void
    {
        if (! $event->captureId) {
            return;
        }

        $payment = $this->paymentByCaptureId($event->captureId);

        if ($payment && $event->amount !== null && Money::toCents($event->amount) !== Money::toCents($payment->capturedAmount())) {
            Log::warning('Payment webhook: partial refund ignored', [
                'capture_id' => $event->captureId,
                'refunded' => $event->amount,
                'captured' => $payment->capturedAmount(),
            ]);

            return;
        }

        $this->setStatusByCaptureId($event->captureId, PaymentStatus::Refunded, OrderStatus::Refunded);
    }

    /**
     * Rückbuchung durch den Zahlungsdienst (z. B. nach einem Konflikt). Wirkt
     * wie eine volle Erstattung, kommt aber nicht vom Shop.
     */
    private function captureReversed(WebhookEvent $event): void
    {
        if (! $event->captureId) {
            return;
        }

        Log::warning('Payment webhook: capture reversed', ['capture_id' => $event->captureId]);

        $this->setStatusByCaptureId($event->captureId, PaymentStatus::Reversed, OrderStatus::Refunded);
    }

    private function paymentByCaptureId(string $captureId): ?Payment
    {
        return Payment::query()->where('paypal_capture_id', $captureId)->with('order')->first();
    }

    private function setStatusByCaptureId(string $captureId, PaymentStatus $paymentStatus, OrderStatus $orderStatus): void
    {
        $payment = $this->paymentByCaptureId($captureId);

        if (! $payment) {
            Log::info('Payment webhook: no payment for capture', ['capture_id' => $captureId]);

            return;
        }

        $this->setStatus($payment, $paymentStatus, $orderStatus);
    }

    /**
     * Setzt Zahlungs- und Bestellstatus in einem Schritt. Ob der
     * Bestell-Übergang erlaubt ist, entscheidet der OrderManager.
     */
    private function setStatus(Payment $payment, PaymentStatus $paymentStatus, OrderStatus $orderStatus): void
    {
        DB::transaction(function () use ($payment, $paymentStatus, $orderStatus): void {
            $payment->update(['status' => $paymentStatus]);

            if ($payment->order) {
                $this->orderManager->transitionTo($payment->order, $orderStatus);
            }
        });
    }
}
