<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Orders\OrderStatus;
use App\Support\Orders\OrderSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerOrderController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $orders = $request->user()
            ->orders()
            ->latest()
            ->get(['id', 'status', 'total_amount', 'created_at']);

        // JSON für das User-Settings-Modal (lädt die Liste per fetch).
        if ($request->wantsJson()) {
            return response()->json(['orders' => $orders]);
        }

        return Inertia::render('settings/Orders', ['orders' => $orders]);
    }

    public function show(Request $request, Order $order): Response|JsonResponse
    {
        abort_unless($order->customer_id === $request->user()->id, 404);

        $order->load([
            'items.product.images' => fn ($query) => $query->where('sort_order', 0),
            'payments',
        ]);

        // Bewertbar sind Artikel bezahlter Bestellungen, deren Produkt es noch
        // gibt; "rated", wenn der Kunde das Produkt schon bewertet hat.
        $ratedProductIds = $request->user()->ratings()->pluck('product_id')->all();
        $ratingState = fn ($item): ?string => match (true) {
            $order->status !== OrderStatus::Paid || $item->product === null => null,
            in_array($item->product_id, $ratedProductIds, true) => 'rated',
            default => 'rateable',
        };

        $summary = OrderSummary::fromOrder($order);

        $payload = [
            'id' => $order->id,
            'status' => $order->status,
            'payment_method' => $order->payment_method,
            'payment_method_label' => $order->payment_method?->label(),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
            'shipping_address' => $order->shipping_address,
            'billing_address' => $order->billing_address,
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'unit_price' => (string) $item->unit_price,
                'quantity' => $item->quantity,
                'image_url' => $item->product?->images->first()?->url,
                'rating' => $ratingState($item),
            ])->values(),
            'summary' => $summary,
            'customer_email' => $request->user()->email,
        ];

        // JSON für die Detailansicht im User-Settings-Modal.
        if ($request->wantsJson()) {
            return response()->json(['order' => $payload]);
        }

        return Inertia::render('settings/Orders/Show', [
            'order' => $payload,
        ]);
    }
}
