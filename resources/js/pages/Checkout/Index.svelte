<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Minus from '@lucide/svelte/icons/minus';
    import Plus from '@lucide/svelte/icons/plus';
    import ShoppingCart from '@lucide/svelte/icons/shopping-cart';
    import X from '@lucide/svelte/icons/x';
    import AppHead from '@/components/AppHead.svelte';
    import ShopHeader from '@/components/ShopHeader.svelte';
    import { Button } from '@/components/ui/button';
    import * as Empty from '@/components/ui/empty';
    import { formatPrice } from '@/lib/currency';
    import cartRoutes from '@/routes/cart';
    import checkout from '@/routes/checkout';
    import type { Cart, CartItem } from '@/types';

    let { cart }: { cart: Cart } = $props();

    function lineArgs(item: CartItem) {
        return item.variant_id
            ? { product: item.product_id, variant: item.variant_id }
            : { product: item.product_id };
    }

    function updateQuantity(item: CartItem, quantity: number) {
        router.patch(
            cartRoutes.items.update.url(lineArgs(item)),
            { quantity },
            { preserveScroll: true, preserveState: true },
        );
    }

    function removeItem(item: CartItem) {
        router.delete(cartRoutes.items.destroy.url(lineArgs(item)), {
            preserveScroll: true,
            preserveState: true,
        });
    }

    function updateShipping(shippingMethod: string) {
        router.patch(
            cartRoutes.shipping.update.url(),
            { shipping_method: shippingMethod },
            { preserveScroll: true, preserveState: true },
        );
    }
</script>

<AppHead title="Warenkorb" />

<div class="flex min-h-screen flex-col bg-muted">
    <ShopHeader />

    <main class="flex-1">
        <div class="mx-auto max-w-7xl px-4 py-8 lg:px-8">
            <h1 class="mb-6 text-3xl font-bold text-foreground">Warenkorb</h1>

            <div class="flex flex-col gap-6 lg:flex-row lg:items-stretch">
                <div class="flex flex-1 flex-col">
                    <div
                        class="flex flex-1 flex-col overflow-hidden rounded-lg border bg-white"
                    >
                        {#if cart.is_empty}
                            <div
                                class="flex flex-1 items-center justify-center py-16"
                            >
                                <Empty.Root>
                                    <Empty.Header>
                                        <Empty.Media variant="icon">
                                            <ShoppingCart />
                                        </Empty.Media>
                                        <Empty.Title
                                            >Ihr Warenkorb ist leer</Empty.Title
                                        >
                                        <Empty.Description>
                                            Noch keine Produkte hinzugefügt.
                                        </Empty.Description>
                                    </Empty.Header>
                                    <Empty.Content>
                                        <Button asChild>
                                            {#snippet children(props)}
                                                <Link
                                                    href="/"
                                                    class={props.class}
                                                >
                                                    Weiter einkaufen
                                                </Link>
                                            {/snippet}
                                        </Button>
                                    </Empty.Content>
                                </Empty.Root>
                            </div>
                        {:else}
                            <table class="w-full text-sm">
                                <thead class="border-b bg-muted">
                                    <tr>
                                        <th
                                            class="px-5 py-3 text-left font-semibold text-muted-foreground"
                                        >
                                            Produkt
                                        </th>
                                        <th
                                            class="px-4 py-3 text-center font-semibold text-muted-foreground"
                                        >
                                            Anzahl
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right font-semibold text-muted-foreground"
                                        >
                                            Stückpreis
                                        </th>
                                        <th
                                            class="px-5 py-3 text-right font-semibold text-muted-foreground"
                                        >
                                            Summe
                                        </th>
                                        <th class="w-10"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    {#each cart.items as item (item.line_key)}
                                        <tr>
                                            <td class="px-5 py-4">
                                                <div
                                                    class="flex items-center gap-3"
                                                >
                                                    <div
                                                        class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded border bg-muted"
                                                    >
                                                        {#if item.image_url}
                                                            <img
                                                                src={item.image_url}
                                                                alt={item.name}
                                                                class="size-full object-cover object-center"
                                                            />
                                                        {:else}
                                                            <div
                                                                class="size-8 rounded bg-muted"
                                                            ></div>
                                                        {/if}
                                                    </div>
                                                    <div>
                                                        <Link
                                                            href={item.product_url}
                                                            class="font-semibold text-foreground hover:text-brand-accent"
                                                        >
                                                            {item.name}
                                                        </Link>
                                                        {#if item.manufacturer_name}
                                                            <p
                                                                class="text-xs text-brand-accent"
                                                            >
                                                                {item.manufacturer_name}
                                                            </p>
                                                        {/if}
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4">
                                                <div
                                                    class="mx-auto flex w-fit items-center rounded border"
                                                >
                                                    <button
                                                        class="flex size-8 items-center justify-center text-muted-foreground hover:bg-muted disabled:opacity-40"
                                                        onclick={() =>
                                                            updateQuantity(
                                                                item,
                                                                item.quantity -
                                                                    1,
                                                            )}
                                                        disabled={item.quantity <=
                                                            1}
                                                    >
                                                        <Minus
                                                            class="size-3.5"
                                                        />
                                                    </button>
                                                    <span
                                                        class="w-10 text-center text-sm font-medium"
                                                    >
                                                        {item.quantity}
                                                    </span>
                                                    <button
                                                        class="flex size-8 items-center justify-center text-muted-foreground hover:bg-muted"
                                                        onclick={() =>
                                                            updateQuantity(
                                                                item,
                                                                item.quantity +
                                                                    1,
                                                            )}
                                                    >
                                                        <Plus
                                                            class="size-3.5"
                                                        />
                                                    </button>
                                                </div>
                                            </td>
                                            <td
                                                class="px-4 py-4 text-right text-sm text-muted-foreground"
                                            >
                                                {formatPrice(item.unit_price)}*
                                            </td>
                                            <td
                                                class="px-5 py-4 text-right text-sm font-semibold text-foreground"
                                            >
                                                {formatPrice(item.line_total)}*
                                            </td>
                                            <td class="pr-3">
                                                <button
                                                    onclick={() =>
                                                        removeItem(item)}
                                                    class="rounded border p-1.5 text-muted-foreground hover:border-border hover:text-muted-foreground"
                                                    aria-label="Entfernen"
                                                >
                                                    <X class="size-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        {/if}
                    </div>
                </div>

                <div class="w-full lg:w-80 xl:w-88">
                    <div class="rounded-lg border bg-white p-5">
                        <h2 class="mb-4 text-lg font-bold text-foreground">
                            Versandart
                        </h2>
                        <div class="flex flex-col gap-3">
                            {#each cart.shipping_methods as method (method.id)}
                                <label
                                    class="flex cursor-pointer items-start gap-3"
                                >
                                    <input
                                        type="radio"
                                        name="shipping"
                                        value={method.id}
                                        checked={method.selected}
                                        onchange={() =>
                                            updateShipping(method.id)}
                                        class="mt-0.5 accent-primary"
                                    />
                                    <span class="text-sm">
                                        <span
                                            class="font-semibold text-foreground"
                                        >
                                            {method.label}
                                        </span>
                                        <span class="text-muted-foreground">
                                            — {formatPrice(method.price ?? 0)}*
                                        </span>
                                        {#if method.description}
                                            <br />
                                            <span class="text-muted-foreground"
                                                >{method.description}</span
                                            >
                                        {/if}
                                    </span>
                                </label>
                            {/each}
                        </div>
                    </div>

                    <div class="mt-4 rounded-lg border bg-white p-5">
                        <h2 class="mb-4 text-xl font-bold text-foreground">
                            Zusammenfassung
                        </h2>

                        <div class="flex flex-col gap-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >Zwischensumme</span
                                >
                                <span class="font-medium"
                                    >{formatPrice(cart.subtotal)}*</span
                                >
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >Versandkosten</span
                                >
                                <span class="font-medium"
                                    >{formatPrice(cart.shipping_total)}*</span
                                >
                            </div>
                            <div class="my-2 border-t pt-2">
                                <div class="flex justify-between">
                                    <span class="font-bold text-foreground"
                                        >Gesamtsumme</span
                                    >
                                    <span class="font-bold text-foreground"
                                        >{formatPrice(cart.total)}*</span
                                    >
                                </div>
                            </div>
                            <div
                                class="flex justify-between text-muted-foreground"
                            >
                                <span>Gesamtnettosumme</span>
                                <span>{formatPrice(cart.net_total)}</span>
                            </div>
                            <div
                                class="flex justify-between text-muted-foreground"
                            >
                                <span>zzgl. {cart.vat_rate} % MwSt.</span>
                                <span>{formatPrice(cart.vat_amount)}</span>
                            </div>
                        </div>

                        <Button
                            asChild
                            class="mt-4 w-full bg-primary text-white hover:bg-primary/90"
                            disabled={cart.is_empty}
                        >
                            {#snippet children(props)}
                                <Link
                                    href={checkout.confirm.url()}
                                    class={props.class}
                                >
                                    Zur Kasse
                                </Link>
                            {/snippet}
                        </Button>
                    </div>

                    <p class="mt-3 text-center text-xs text-muted-foreground">
                        * Preise inkl. MwSt. zzgl. Versandkosten
                    </p>
                </div>
            </div>
        </div>
    </main>
</div>
