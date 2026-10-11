<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Minus from '@lucide/svelte/icons/minus';
    import Plus from '@lucide/svelte/icons/plus';
    import ShoppingCart from '@lucide/svelte/icons/shopping-cart';
    import X from '@lucide/svelte/icons/x';
    import AppHead from '@/components/AppHead.svelte';
    import CartLayoutBar from '@/components/cart-page/CartLayoutBar.svelte';
    import CartLayoutReceipt from '@/components/cart-page/CartLayoutReceipt.svelte';
    import CartLayoutRows from '@/components/cart-page/CartLayoutRows.svelte';
    import CartLayoutShippingFirst from '@/components/cart-page/CartLayoutShippingFirst.svelte';
    import CartLayoutStacked from '@/components/cart-page/CartLayoutStacked.svelte';
    import CartLayoutStandard from '@/components/cart-page/CartLayoutStandard.svelte';
    import ShopHeader from '@/components/ShopHeader.svelte';
    import { Button } from '@/components/ui/button';
    import * as Empty from '@/components/ui/empty';
    import VariantSwitcher from '@/components/VariantSwitcher.svelte';
    import { CART_VARIANTS } from '@/lib/cartPage';
    import { formatPrice } from '@/lib/currency';
    import { useLayoutVariant } from '@/lib/useLayoutVariant.svelte';
    import cartRoutes from '@/routes/cart';
    import checkout from '@/routes/checkout';
    import type { Cart, CartItem, CartPageFields } from '@/types';

    let { cart }: { cart: Cart } = $props();

    // Layout-Variante: Auswahl steht in der URL (?variant=B); ohne Parameter gilt A.
    const layoutVariant = useLayoutVariant(CART_VARIANTS);

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

    // Die Snippets werden erst beim Rendern ausgewertet, nicht beim Initialisieren.
    const fields: CartPageFields = $derived({
        empty: emptyState,
        items: itemsField,
        shipping: shippingField,
        totals: totalsField,
        checkout: checkoutField,
        totalLine: totalLineField,
        note: noteField,
    });
</script>

{#snippet emptyState()}
    <div class="flex flex-1 items-center justify-center py-16">
        <Empty.Root>
            <Empty.Header>
                <Empty.Media variant="icon">
                    <ShoppingCart />
                </Empty.Media>
                <Empty.Title>Ihr Warenkorb ist leer</Empty.Title>
                <Empty.Description>
                    Noch keine Produkte hinzugefügt.
                </Empty.Description>
            </Empty.Header>
            <Empty.Content>
                <Button asChild>
                    {#snippet children(props)}
                        <Link href="/" class={props.class}>
                            Weiter einkaufen
                        </Link>
                    {/snippet}
                </Button>
            </Empty.Content>
        </Empty.Root>
    </div>
{/snippet}

{#snippet quantityStepper(item: CartItem)}
    <div class="flex w-fit items-center rounded border">
        <button
            class="flex size-8 items-center justify-center text-muted-foreground hover:bg-muted disabled:opacity-40"
            aria-label="Menge verringern"
            onclick={() => updateQuantity(item, item.quantity - 1)}
            disabled={item.quantity <= 1}
        >
            <Minus class="size-3.5" />
        </button>
        <span class="w-10 text-center text-sm font-medium">
            {item.quantity}
        </span>
        <button
            class="flex size-8 items-center justify-center text-muted-foreground hover:bg-muted"
            aria-label="Menge erhöhen"
            onclick={() => updateQuantity(item, item.quantity + 1)}
        >
            <Plus class="size-3.5" />
        </button>
    </div>
{/snippet}

{#snippet itemThumb(item: CartItem)}
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
            <div class="size-8 rounded bg-muted"></div>
        {/if}
    </div>
{/snippet}

{#snippet itemName(item: CartItem)}
    <div>
        <Link
            href={item.product_url}
            class="font-semibold text-foreground hover:text-brand-accent"
        >
            {item.name}
        </Link>
        {#if item.manufacturer_name}
            <p class="text-xs text-brand-accent">
                {item.manufacturer_name}
            </p>
        {/if}
    </div>
{/snippet}

{#snippet removeButton(item: CartItem)}
    <button
        onclick={() => removeItem(item)}
        class="rounded border p-1.5 text-muted-foreground hover:border-border hover:text-muted-foreground"
        aria-label="Entfernen"
    >
        <X class="size-4" />
    </button>
{/snippet}

{#snippet itemsField(style: 'table' | 'rows' = 'table')}
    {#if style === 'rows'}
        <ul class="divide-y">
            {#each cart.items as item (item.line_key)}
                <li
                    class="flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-4"
                >
                    {@render itemThumb(item)}
                    <div class="min-w-0 flex-1 basis-40">
                        {@render itemName(item)}
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {formatPrice(item.unit_price)}* pro Stück
                        </p>
                    </div>
                    {@render quantityStepper(item)}
                    <span
                        class="w-24 text-right text-sm font-semibold text-foreground"
                    >
                        {formatPrice(item.line_total)}*
                    </span>
                    {@render removeButton(item)}
                </li>
            {/each}
        </ul>
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
                            <div class="flex items-center gap-3">
                                {@render itemThumb(item)}
                                {@render itemName(item)}
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <div class="mx-auto w-fit">
                                {@render quantityStepper(item)}
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
                            {@render removeButton(item)}
                        </td>
                    </tr>
                {/each}
            </tbody>
        </table>
    {/if}
{/snippet}

{#snippet shippingField(style: 'list' | 'cards' = 'list')}
    <h2 class="mb-4 text-lg font-bold text-foreground">Versandart</h2>
    {#if style === 'cards'}
        <div
            class="grid gap-3 sm:grid-cols-[repeat(auto-fit,minmax(14rem,1fr))]"
        >
            {#each cart.shipping_methods as method (method.id)}
                <label
                    class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-colors has-[:checked]:border-brand-accent has-[:checked]:bg-brand-accent/5"
                >
                    <input
                        type="radio"
                        name="shipping"
                        value={method.id}
                        checked={method.selected}
                        onchange={() => updateShipping(method.id)}
                        class="mt-0.5 accent-primary"
                    />
                    <span class="text-sm">
                        <span class="block font-semibold text-foreground">
                            {method.label}
                        </span>
                        <span class="block text-muted-foreground">
                            {formatPrice(method.price ?? 0)}*
                        </span>
                        {#if method.description}
                            <span class="mt-1 block text-muted-foreground">
                                {method.description}
                            </span>
                        {/if}
                    </span>
                </label>
            {/each}
        </div>
    {:else}
        <div class="flex flex-col gap-3">
            {#each cart.shipping_methods as method (method.id)}
                <label class="flex cursor-pointer items-start gap-3">
                    <input
                        type="radio"
                        name="shipping"
                        value={method.id}
                        checked={method.selected}
                        onchange={() => updateShipping(method.id)}
                        class="mt-0.5 accent-primary"
                    />
                    <span class="text-sm">
                        <span class="font-semibold text-foreground">
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
    {/if}
{/snippet}

{#snippet totalsField()}
    <h2 class="mb-4 text-xl font-bold text-foreground">Zusammenfassung</h2>

    <div class="flex flex-col gap-2 text-sm">
        <div class="flex justify-between">
            <span class="text-muted-foreground">Zwischensumme</span>
            <span class="font-medium">{formatPrice(cart.subtotal)}*</span>
        </div>
        <div class="flex justify-between">
            <span class="text-muted-foreground">Versandkosten</span>
            <span class="font-medium">{formatPrice(cart.shipping_total)}*</span>
        </div>
        <div class="my-2 border-t pt-2">
            <div class="flex justify-between">
                <span class="font-bold text-foreground">Gesamtsumme</span>
                <span class="font-bold text-foreground"
                    >{formatPrice(cart.total)}*</span
                >
            </div>
        </div>
        <div class="flex justify-between text-muted-foreground">
            <span>Gesamtnettosumme</span>
            <span>{formatPrice(cart.net_total)}</span>
        </div>
        <div class="flex justify-between text-muted-foreground">
            <span>zzgl. {cart.vat_rate} % MwSt.</span>
            <span>{formatPrice(cart.vat_amount)}</span>
        </div>
    </div>
{/snippet}

{#snippet totalLineField()}
    <div class="flex items-baseline gap-3">
        <span class="text-sm text-muted-foreground">Gesamtsumme</span>
        <span class="text-xl font-bold text-foreground"
            >{formatPrice(cart.total)}*</span
        >
    </div>
{/snippet}

{#snippet checkoutField()}
    {#if cart.is_empty}
        <Button class="mt-4 w-full bg-primary text-white" disabled>
            Zur Kasse
        </Button>
    {:else}
        <Button
            asChild
            class="mt-4 w-full bg-primary text-white hover:bg-primary/90"
        >
            {#snippet children(props)}
                <Link href={checkout.confirm.url()} class={props.class}>
                    Zur Kasse
                </Link>
            {/snippet}
        </Button>
    {/if}
{/snippet}

{#snippet noteField()}
    <p class="mt-3 text-center text-xs text-muted-foreground">
        * Preise inkl. MwSt. zzgl. Versandkosten
    </p>
{/snippet}

<AppHead title="Warenkorb" />

<div class="flex min-h-screen flex-col bg-muted">
    <ShopHeader />

    <main class="flex-1">
        <div class="mx-auto max-w-7xl px-4 py-8 lg:px-8">
            <h1 class="mb-6 text-3xl font-bold text-foreground">Warenkorb</h1>

            {#if layoutVariant.current === 'B'}
                <CartLayoutRows {fields} isEmpty={cart.is_empty} />
            {:else if layoutVariant.current === 'C'}
                <CartLayoutStacked {fields} isEmpty={cart.is_empty} />
            {:else if layoutVariant.current === 'D'}
                <CartLayoutShippingFirst {fields} isEmpty={cart.is_empty} />
            {:else if layoutVariant.current === 'E'}
                <CartLayoutBar {fields} isEmpty={cart.is_empty} />
            {:else if layoutVariant.current === 'F'}
                <CartLayoutReceipt {fields} isEmpty={cart.is_empty} />
            {:else}
                <CartLayoutStandard {fields} isEmpty={cart.is_empty} />
            {/if}
        </div>
    </main>
</div>

<VariantSwitcher
    options={CART_VARIANTS}
    current={layoutVariant.current}
    onselect={layoutVariant.select}
/>
