<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PayPalService;
use App\Support\Orders\OrderManager;
use App\Support\Orders\OrderStatus;
use App\Support\Orders\OrderSummary;
use App\Support\Orders\PaymentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Orders/Index', [
            'orders' => Order::with('customer')->latest()->get(),
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load([
            'customer',
            'items.product.images' => fn ($query) => $query->where('sort_order', 0),
            'payments',
        ]);

        return Inertia::render('Admin/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'status' => $order->status,
                'payment_method' => $order->payment_method,
                'total_amount' => $order->total_amount,
                'shipping_amount' => $order->shipping_amount,
                'shipping_address' => $order->shipping_address,
                'billing_address' => $order->billing_address,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
                'customer' => $order->customer ? [
                    'id' => $order->customer->id,
                    'name' => $order->customer->name,
                    'email' => $order->customer->email,
                ] : null,
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'unit_price' => (string) $item->unit_price,
                    'quantity' => $item->quantity,
                    'image_url' => $item->product?->images->first()?->url,
                ])->values(),
                // Explizites Mapping statt der rohen Models: der komplette
                // PayPal-API-Response (payments.response_data, inkl. Payer-
                // Rohdaten) gehört nicht in die Frontend-Props.
                'payments' => $order->payments->map(fn (Payment $payment) => [
                    'id' => $payment->id,
                    'status' => $payment->status,
                    'paypal_order_id' => $payment->paypal_order_id,
                    'paypal_capture_id' => $payment->paypal_capture_id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'payer_email' => $payment->payer_email,
                    'payer_name' => $payment->payer_name,
                    'created_at' => $payment->created_at,
                ])->values(),
                'summary' => OrderSummary::fromOrder($order),
            ],
        ]);
    }

    /**
     * Confirm manual receipt of payment for an invoice order (bank transfer
     * or direct debit). This is the only order-status transition an admin
     * may trigger by hand: PayPal orders are exclusively managed by the
     * capture flow and webhook (see PayPalController) and must never be
     * edited here.
     */
    public function confirmPayment(Request $request, Order $order, OrderManager $orderManager): JsonResponse
    {
        if (! $orderManager->canConfirmInvoicePayment($order)) {
            return response()->json([
                'message' => 'Zahlungseingang kann nur für offene Rechnungsbestellungen bestätigt werden.',
            ], 422);
        }

        $validated = $request->validate([
            'notify' => ['sometimes', 'boolean'],
        ]);

        if ($validated['notify'] ?? false) {
            // Dedicated invoice-payment flow: sets the status and notifies
            // only the customer, with a payment-confirmation mail (not the
            // generic order-confirmation, which still asks to transfer money).
            $orderManager->confirmInvoicePayment($order);
        } else {
            $orderManager->transitionTo($order, OrderStatus::Paid);
        }

        return response()->json([
            'message' => 'Zahlungseingang bestätigt.',
            'status' => $order->fresh()->status,
        ]);
    }

    /**
     * Refund the captured PayPal payment of an order.
     */
    public function refund(Order $order, PayPalService $payPalService, OrderManager $orderManager): JsonResponse
    {
        /** @var Payment|null $payment */
        $payment = $order->payments()
            // MISMATCH: eingezogen, aber Betrag/Währung weichen ab - das Geld
            // muss zurück, die Bestellung wird nicht erfüllt.
            ->whereIn('status', [PaymentStatus::Completed, PaymentStatus::Mismatch])
            ->whereNotNull('paypal_capture_id')
            ->latest()
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'Keine erstattbare PayPal-Zahlung gefunden.'], 422);
        }

        try {
            $response = $payPalService->refundOrder($payment->paypal_capture_id, $payment->capturedAmount(), $payment->currency ?: 'EUR');

            // Das SDK meldet API-Fehler als Array statt als Exception: ohne
            // Statusprüfung würde eine abgelehnte Erstattung als erfolgreich
            // gebucht.
            $refundStatus = $response['status'] ?? null;

            if ($refundStatus === 'PENDING') {
                Log::warning('PayPal refund pending', [
                    'order_id' => $order->id,
                    'capture_id' => $payment->paypal_capture_id,
                ]);

                return response()->json([
                    'message' => 'Erstattung bei PayPal angestoßen, aber noch nicht abgeschlossen. Der Status wird automatisch aktualisiert.',
                    'status' => 'pending',
                ], 202);
            }

            if ($refundStatus !== 'COMPLETED') {
                Log::error('PayPal refund not confirmed', [
                    'order_id' => $order->id,
                    'capture_id' => $payment->paypal_capture_id,
                    'response' => $response,
                ]);

                return response()->json(['message' => 'Erstattung fehlgeschlagen. Bitte prüfen Sie das Log und den PayPal-Status.'], 422);
            }

            DB::transaction(function () use ($payment, $order, $orderManager): void {
                $payment->update(['status' => PaymentStatus::Refunded]);
                $orderManager->transitionTo($order, OrderStatus::Refunded);
            });

            return response()->json(['message' => 'Zahlung erstattet.', 'status' => OrderStatus::Refunded]);
        } catch (\Throwable $e) {
            Log::error('PayPal refund failed', [
                'order_id' => $order->id,
                'capture_id' => $payment->paypal_capture_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Erstattung fehlgeschlagen. Bitte prüfen Sie das Log und den PayPal-Status.'], 422);
        }
    }
}
