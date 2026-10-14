<?php

namespace App\Support\Cart;

use App\Contracts\CartStore;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Liest und schreibt den Warenkorb. In der Session liegt nur der schlanke
 * Rohzustand (CartState), cart() baut daraus das typisierte Cart-DTO.
 *
 * @phpstan-type CartState array{items: array<int|string, int>, shipping_method: string, shipping_address: array<string, mixed>, billing_address: array<string, mixed>|null}
 */
class CartService
{
    public const DEFAULT_ADDRESS = CartAddress::DEFAULTS;

    public function __construct(
        private readonly CartStore $store,
    ) {}

    public function cart(): Cart
    {
        $state = $this->state();
        $items = $this->items($state['items']);

        if ($items === [] && $state['items'] !== []) {
            $state['items'] = [];
            $this->persist($state);
        }

        return new Cart(
            items: $items,
            vatRate: (int) config('shop.cart.vat_rate', 19),
            shippingMethods: $this->shippingMethods($state['shipping_method']),
            shippingAddress: CartAddress::fromArray($state['shipping_address']),
            billingAddress: $state['billing_address'] !== null ? CartAddress::fromArray($state['billing_address']) : null,
        );
    }

    public function add(Product $product, int $quantity, ?ProductVariant $variant = null): void
    {
        $state = $this->state();
        $lineKey = $this->lineKey((int) $product->getKey(), $variant?->getKey());
        $existingQuantity = (int) ($state['items'][$lineKey] ?? 0);

        $state['items'][$lineKey] = min(99, $existingQuantity + $quantity);

        $this->persist($state);
    }

    public function updateQuantity(Product $product, int $quantity, ?ProductVariant $variant = null): void
    {
        $state = $this->state();
        $state['items'][$this->lineKey((int) $product->getKey(), $variant?->getKey())] = min(99, max(1, $quantity));

        $this->persist($state);
    }

    public function remove(Product $product, ?ProductVariant $variant = null): void
    {
        $state = $this->state();
        unset($state['items'][$this->lineKey((int) $product->getKey(), $variant?->getKey())]);

        $this->persist($state);
    }

    /**
     * Cart lines are keyed "productId" (no variant) or "productId:variantId",
     * so the same product can sit in the cart once per variant.
     */
    private function lineKey(int $productId, ?int $variantId): string
    {
        return $variantId ? "{$productId}:{$variantId}" : (string) $productId;
    }

    /**
     * @return array{0: int, 1: int|null} [productId, variantId]
     */
    private function parseLineKey(string $lineKey): array
    {
        [$productId, $variantId] = array_pad(explode(':', $lineKey, 2), 2, null);

        return [(int) $productId, ((int) $variantId) > 0 ? (int) $variantId : null];
    }

    public function setShippingMethod(string $shippingMethod): void
    {
        $state = $this->state();
        $state['shipping_method'] = $shippingMethod;

        $this->persist($state);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function setShippingAddress(array $data): void
    {
        $state = $this->state();
        $state['shipping_address'] = array_merge(self::DEFAULT_ADDRESS, $data);

        $this->persist($state);
    }

    /**
     * @param  array<string, mixed>|null  $data  null = wie Lieferadresse
     */
    public function setBillingAddress(?array $data): void
    {
        $state = $this->state();
        $state['billing_address'] = $data !== null
            ? array_merge(self::DEFAULT_ADDRESS, $data)
            : null;

        $this->persist($state);
    }

    public function clear(): void
    {
        $this->persist([
            'items' => [],
            'shipping_method' => ShippingMethods::default()->key,
            'shipping_address' => self::DEFAULT_ADDRESS,
            'billing_address' => null,
        ]);
    }

    /**
     * @param  array<int|string, int>  $rawItems  lineKey => Menge (numerische Keys macht PHP zu int)
     * @return list<CartItem>
     */
    private function items(array $rawItems): array
    {
        if ($rawItems === []) {
            return [];
        }

        $productIds = collect(array_keys($rawItems))
            ->map(fn (int|string $lineKey): int => $this->parseLineKey((string) $lineKey)[0])
            ->unique()
            ->all();

        $products = Product::query()
            ->with('manufacturer', 'variants')
            ->with(['images' => fn ($query) => $query->where('sort_order', 0)])
            ->whereKey($productIds)
            ->get()
            ->keyBy(fn (Product $product) => (string) $product->getKey());

        return array_values(collect($rawItems)
            ->map(function (int $quantity, int|string $lineKey) use ($products): ?CartItem {
                $lineKey = (string) $lineKey;
                [$productId, $variantId] = $this->parseLineKey($lineKey);

                /** @var Product|null $product */
                $product = $products->get((string) $productId);

                if ($quantity < 1) {
                    return null;
                }

                /** @var ProductVariant|null $variant */
                $variant = $variantId !== null ? $product?->variants->firstWhere('id', $variantId) : null;
                // A chosen variant that no longer exists makes the line unbuyable.
                $variantMissing = $variantId !== null && $variant === null;
                // The variant requirement is enforced at add-time (AddCartItemRequest),
                // but a line added BEFORE variants existed would otherwise stay buyable
                // at the base price. If the product now has variants and none is chosen,
                // the price is ambiguous -> mark the line unbuyable so checkout blocks it.
                $variantRequiredButMissing = $variantId === null
                    && $product !== null
                    && $product->variants->isNotEmpty();

                $unitPriceCents = Money::toCents($variant->price ?? $product->price ?? 0);

                $name = (string) ($product->name ?? 'Produkt nicht verfügbar');

                // The variant label becomes part of the line name so cart UI,
                // checkout, order snapshot and mails all show it consistently.
                if ($variant !== null) {
                    $name .= ' – '.$variant->label;
                }

                return new CartItem(
                    lineKey: $lineKey,
                    productId: $productId,
                    variantId: $variantId,
                    variantLabel: $variant?->label,
                    name: $name,
                    description: $product?->description,
                    productNumber: (string) ($product->id ?? $productId),
                    manufacturerName: $product?->manufacturer?->name,
                    quantity: $quantity,
                    unitPriceCents: $unitPriceCents,
                    imageUrl: $product?->images->first()?->url,
                    productUrl: $product?->url() ?? route('home'),
                    isAvailable: $product !== null && $product->is_available && ! $variantMissing && ! $variantRequiredButMissing,
                );
            })
            ->filter()
            ->all());
    }

    /**
     * @return list<ShippingOption>
     */
    private function shippingMethods(string $selectedKey): array
    {
        return array_map(
            fn (ShippingMethod $method): ShippingOption => new ShippingOption(
                id: $method->key,
                label: $method->name,
                description: $method->description,
                priceCents: $method->priceCents,
                selected: $method->key === $selectedKey,
            ),
            ShippingMethods::all(),
        );
    }

    public function hasUnavailableItems(): bool
    {
        foreach ($this->items($this->state()['items']) as $item) {
            if (! $item->isAvailable) {
                return true;
            }
        }

        return false;
    }

    public function isAddressComplete(): bool
    {
        return CartAddress::fromArray($this->state()['shipping_address'])->isComplete();
    }

    /**
     * Rohzustand aus der Session, bereinigt und mit Defaults aufgefüllt.
     *
     * @return CartState
     */
    private function state(): array
    {
        $rawState = $this->store->get();
        $shippingMethodKeys = ShippingMethods::keys();

        $items = [];
        $rawItems = is_array($rawState['items'] ?? null) ? $rawState['items'] : [];

        foreach ($rawItems as $lineKey => $item) {
            [$productId, $variantId] = $this->parseLineKey((string) $lineKey);
            // Older sessions stored a {quantity, unit_price, name, product_number}
            // shape per item; only the quantity still matters going forward.
            $normalizedQuantity = (int) (is_array($item) ? ($item['quantity'] ?? 0) : $item);

            if ($productId < 1 || $normalizedQuantity < 1) {
                continue;
            }

            $items[$this->lineKey($productId, $variantId)] = min(99, $normalizedQuantity);
        }

        return [
            'items' => $items,
            // Unbekannte Keys (umbenannt, entfernt oder alte numerische IDs
            // aus der früheren Tabelle) fallen auf die Standard-Versandart.
            'shipping_method' => in_array(($rawState['shipping_method'] ?? null), $shippingMethodKeys, true)
                ? (string) $rawState['shipping_method']
                : $shippingMethodKeys[0],
            'shipping_address' => array_merge(
                self::DEFAULT_ADDRESS,
                $rawState['shipping_address'] ?? []
            ),
            'billing_address' => isset($rawState['billing_address']) && is_array($rawState['billing_address'])
                ? array_merge(self::DEFAULT_ADDRESS, $rawState['billing_address'])
                : null,
        ];
    }

    /**
     * @param  CartState  $state
     */
    private function persist(array $state): void
    {
        $this->store->put($state);
    }
}
