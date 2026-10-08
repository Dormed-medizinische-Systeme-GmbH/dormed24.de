<?php

namespace App\Support\Orders\Gateway;

use Illuminate\Http\Request;

/**
 * Seam zum Zahlungsdienst (heute PayPal). Der Adapter spricht mit dem Dienst
 * und übersetzt dessen Antworten in neutrale Ergebnisse; er schreibt keine
 * Models. Was daraus für Zahlung und Bestellung folgt, entscheidet der
 * PaymentReconciliation.
 */
interface PaymentGateway
{
    /**
     * Legt die Zahlung beim Zahlungsdienst an.
     *
     * @param  array<string, string|null>  $shippingAddress
     *
     * @throws \Throwable bei Netzwerk- oder Authentifizierungsfehlern
     */
    public function createPayment(int $amountCents, string $currency, array $shippingAddress): CreatedPayment;

    /**
     * Zieht eine vom Käufer freigegebene Zahlung ein.
     *
     * @throws \Throwable bei Netzwerk- oder Authentifizierungsfehlern
     */
    public function capture(string $orderReference): CaptureResult;

    /**
     * Erstattet eine Capture vollständig.
     *
     * @throws \Throwable bei Netzwerk- oder Authentifizierungsfehlern
     */
    public function refund(string $captureId, float $amount, string $currency): RefundResult;

    /**
     * Prüft, ob die Webhook-Anfrage wirklich vom Zahlungsdienst stammt.
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * Übersetzt ein verifiziertes Webhook-Event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function parseWebhook(array $payload): WebhookEvent;
}
