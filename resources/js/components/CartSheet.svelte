<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import ChevronLeft from '@lucide/svelte/icons/chevron-left';
    import Minus from '@lucide/svelte/icons/minus';
    import Plus from '@lucide/svelte/icons/plus';
    import ShoppingCart from '@lucide/svelte/icons/shopping-cart';
    import X from '@lucide/svelte/icons/x';
    import { Button } from '@/components/ui/button';
    import {
        Sheet,
        SheetClose,
        SheetContent,
        SheetTitle,
        SheetTrigger,
    } from '@/components/ui/sheet';
    import { formatPrice } from '@/lib/currency';
    import cartRoutes from '@/routes/cart';
    import type { Cart, CartItem } from '@/types';

    let { cart }: { cart: Cart } = $props();

    const formattedTotal = $derived(formatPrice(cart.total));

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
</script>

<Sheet>
    <SheetTrigger asChild>
        {#snippet children(props)}
            <Button variant="ghost" class="gap-2 px-3" onclick={props.onclick}>
                <span class="text-sm font-medium text-brand-secondary">
                    {formattedTotal}*
                </span>
                <div class="relative">
                    <ShoppingCart class="size-5" />
                    {#if cart.count > 0}
                        <span
                            class="absolute -right-2 -top-2 flex size-4 items-center justify-center rounded-full bg-brand-accent text-[10px] font-bold text-white"
                        >
                            {cart.count}
                        </span>
                    {/if}
                </div>
            </Button>
        {/snippet}
    </SheetTrigger>

    <SheetContent
        side="right"
        hideClose
        class="flex w-full flex-col gap-0 p-0 sm:max-w-105"
    >
        <SheetTitle class="sr-only">Warenkorb</SheetTitle>

        <SheetClose asChild>
            {#snippet children(props)}
                <button
                    onclick={props.onClick as () => void}
                    class="flex w-full items-center gap-2 border-b bg-muted px-4 py-3 text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    <ChevronLeft class="size-4" />
                    Weiter einkaufen
                </button>
            {/snippet}
        </SheetClose>

        <div class="flex-1 overflow-y-auto px-5 py-4">
            <div class="mb-5 flex items-baseline justify-between">
                <h2 class="text-xl font-bold text-foreground">Warenkorb</h2>
                <span class="text-sm text-muted-foreground"
                    >{cart.count} Artikel</span
                >
            </div>

            {#if cart.is_empty}
                <div
                    class="rounded-lg border bg-white px-6 py-10 text-center text-sm text-muted-foreground"
                >
                    Ihr Warenkorb ist leer.
                </div>
            {:else}
                {#each cart.items as item (item.line_key)}
                    <div class="mb-4 rounded-lg border bg-white p-3">
                        <div class="flex gap-3">
                            <div
                                class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded border bg-muted"
                            >
                                {#if item.image_url}
                                    <img
                                        src={item.image_url}
                                        alt={item.name}
                                        class="size-full object-cover object-center"
                                    />
                                {:else}
                                    <ShoppingCart
                                        class="size-6 text-muted-foreground"
                                    />
                                {/if}
                            </div>

                            <div class="min-w-0 flex-1">
                                <div
                                    class="flex items-start justify-between gap-2"
                                >
                                    <Link
                                        href={item.product_url}
                                        class="text-sm font-semibold leading-snug text-foreground hover:text-brand-accent"
                                    >
                                        {item.name}
                                    </Link>
                                    <button
                                        class="shrink-0 rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-muted-foreground"
                                        aria-label="Entfernen"
                                        onclick={() => removeItem(item)}
                                    >
                                        <X class="size-4" />
                                    </button>
                                </div>
                                {#if item.manufacturer_name}
                                    <p
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        {item.manufacturer_name}
                                    </p>
                                {/if}
                            </div>
                        </div>

                        <div class="mt-3 flex items-center justify-between">
                            <div class="flex items-center rounded border">
                                <button
                                    class="flex size-8 items-center justify-center text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-40"
                                    onclick={() =>
                                        updateQuantity(item, item.quantity - 1)}
                                    disabled={item.quantity <= 1}
                                >
                                    <Minus class="size-3.5" />
                                </button>
                                <span
                                    class="w-8 text-center text-sm font-medium"
                                >
                                    {item.quantity}
                                </span>
                                <button
                                    class="flex size-8 items-center justify-center text-muted-foreground hover:bg-muted hover:text-foreground"
                                    onclick={() =>
                                        updateQuantity(item, item.quantity + 1)}
                                >
                                    <Plus class="size-3.5" />
                                </button>
                            </div>
                            <span class="text-sm font-semibold text-foreground">
                                {formatPrice(item.line_total)}*
                            </span>
                        </div>
                    </div>
                {/each}
            {/if}

            <div class="mt-4 flex flex-col gap-2 border-t pt-4 text-sm">
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Zwischensumme</span>
                    <span class="font-semibold"
                        >{formatPrice(cart.subtotal)}*</span
                    >
                </div>
                <div class="flex justify-between">
                    <div>
                        <span class="text-muted-foreground">Versandkosten</span>
                        <p class="text-xs text-muted-foreground">
                            {cart.selected_shipping_method?.label}
                        </p>
                    </div>
                    <span class="font-semibold"
                        >+ {formatPrice(cart.shipping_total)}*</span
                    >
                </div>
            </div>
        </div>

        <div class="border-t bg-white px-5 pb-6 pt-4">
            <SheetClose asChild>
                {#snippet children(closeProps)}
                    <Button
                        asChild
                        class="w-full bg-primary text-white hover:bg-primary/90"
                        disabled={cart.is_empty}
                    >
                        {#snippet children(btnProps)}
                            <Link
                                href={cartRoutes.index.url()}
                                class={btnProps.class}
                                onclick={closeProps.onClick as () => void}
                            >
                                Zur Kasse
                            </Link>
                        {/snippet}
                    </Button>
                    <p class="mt-3 text-xs text-muted-foreground">
                        * Preise inkl. MwSt. zzgl. Versandkosten
                    </p>
                {/snippet}
            </SheetClose>
            <div class="mt-3 text-center">
                <Link
                    href={cartRoutes.index.url()}
                    class="text-sm font-semibold text-brand-secondary hover:underline"
                >
                    Warenkorb anzeigen
                </Link>
            </div>
        </div>
    </SheetContent>
</Sheet>
