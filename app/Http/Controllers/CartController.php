<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Requests\Cart\UpdateCartShippingMethodRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Cart\CartService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Checkout/Index', [
            'cart' => $this->cartService->cart()->toArray(),
        ]);
    }

    public function store(AddCartItemRequest $request): RedirectResponse
    {
        $product = Product::query()->findOrFail($request->integer('product_id'));

        $variant = null;

        if ($request->filled('variant_id')) {
            /** @var ProductVariant $variant */
            $variant = $product->variants()->findOrFail($request->integer('variant_id'));
        }

        $this->cartService->add($product, $request->integer('quantity'), $variant);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Produkt wurde in den Warenkorb gelegt.']);

        return back();
    }

    public function update(UpdateCartItemRequest $request, Product $product, ?ProductVariant $variant = null): RedirectResponse
    {
        $this->assertVariantBelongsToProduct($product, $variant);

        $this->cartService->updateQuantity($product, $request->integer('quantity'), $variant);

        return back();
    }

    public function destroy(Product $product, ?ProductVariant $variant = null): RedirectResponse
    {
        $this->assertVariantBelongsToProduct($product, $variant);

        $this->cartService->remove($product, $variant);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Produkt wurde aus dem Warenkorb entfernt.']);

        return back();
    }

    private function assertVariantBelongsToProduct(Product $product, ?ProductVariant $variant): void
    {
        abort_unless($variant === null || $variant->product_id === $product->id, 404);
    }

    public function updateShipping(UpdateCartShippingMethodRequest $request): RedirectResponse
    {
        $this->cartService->setShippingMethod($request->string('shipping_method')->toString());

        return back();
    }
}
