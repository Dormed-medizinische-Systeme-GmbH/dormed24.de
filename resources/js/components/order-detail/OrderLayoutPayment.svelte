<script lang="ts">
    import OrderItemThumb from '@/components/order-detail/OrderItemThumb.svelte';
    import OrderStatusPill from '@/components/order-detail/OrderStatusPill.svelte';
    import OrderTotals from '@/components/order-detail/OrderTotals.svelte';
    import { formatPrice } from '@/lib/currency';
    import {
        formatAddress,
        formatOrderedAt,
        paymentStatusLabel,
    } from '@/lib/orderDetail';
    import type { OrderDetail } from '@/types';

    let { order }: { order: OrderDetail } = $props();

    const orderedAt = $derived(formatOrderedAt(order.created_at));

    function formatDateTime(iso: string): string {
        return new Date(iso).toLocaleString('de-DE', {
            dateStyle: 'medium',
            timeStyle: 'short',
        });
    }
</script>

<!-- Variante E: Zahlung im Fokus – Betrag und Transaktions-Verlauf zuerst -->
<div class="flex flex-1 flex-col gap-6 p-4 pb-20">
    <div class="grid items-start gap-6 lg:grid-cols-[22rem_minmax(0,1fr)]">
        <section
            class="flex flex-col gap-6 rounded-2xl border bg-card p-6 shadow-sm"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-sm font-medium text-muted-foreground">
                        Bestellung #{order.id}
                    </h1>
                    <p class="text-xs text-muted-foreground">vom {orderedAt}</p>
                </div>
                <OrderStatusPill status={order.status} size="sm" />
            </div>

            <div>
                <p class="text-xs text-muted-foreground">Gesamtsumme</p>
                <p class="text-4xl font-semibold tabular-nums">
                    {formatPrice(order.total_amount)}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Versandkosten {formatPrice(order.shipping_amount)}
                </p>
            </div>

            <OrderTotals summary={order.summary} />

            <div>
                <h2 class="mb-3 text-sm font-semibold">Transaktionen</h2>
                {#if order.payments.length > 0}
                    <ol class="relative ml-2 border-l pl-5">
                        {#each order.payments as payment (payment.id)}
                            <li class="relative pb-5 last:pb-0">
                                <span
                                    class="absolute top-1 -left-[1.62rem] size-2.5 rounded-full bg-primary ring-4 ring-card"
                                ></span>
                                <p class="text-sm font-medium">
                                    {paymentStatusLabel(payment.status)}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {formatDateTime(payment.created_at)} · {formatPrice(
                                        payment.amount,
                                    )}
                                </p>
                                {#if payment.payer_email}
                                    <p class="text-xs text-muted-foreground">
                                        {payment.payer_name ??
                                            payment.payer_email}
                                    </p>
                                {/if}
                                {#if payment.paypal_order_id}
                                    <p
                                        class="font-mono text-xs text-muted-foreground"
                                    >
                                        {payment.paypal_order_id}
                                    </p>
                                {/if}
                            </li>
                        {/each}
                    </ol>
                {:else}
                    <p class="text-sm text-muted-foreground">
                        Keine Transaktionen.
                    </p>
                {/if}
            </div>
        </section>

        <div class="flex min-w-0 flex-col gap-6">
            <section>
                <h2 class="mb-3 text-sm font-semibold">Positionen</h2>
                {#if order.items.length > 0}
                    <div class="grid gap-3 sm:grid-cols-2">
                        {#each order.items as item (item.id)}
                            <div
                                class="flex flex-col gap-3 rounded-xl border bg-card p-3"
                            >
                                <OrderItemThumb {item} class="h-36 w-full" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">
                                        {item.product_name}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {item.quantity} × {formatPrice(
                                            item.unit_price,
                                        )}*
                                    </p>
                                </div>
                                <span class="text-sm font-semibold">
                                    {formatPrice(
                                        (
                                            Number(item.unit_price) *
                                            item.quantity
                                        ).toFixed(2),
                                    )}*
                                </span>
                            </div>
                        {/each}
                    </div>
                {:else}
                    <p class="text-sm text-muted-foreground">
                        Keine Positionen vorhanden.
                    </p>
                {/if}
            </section>

            <section
                class="grid gap-px overflow-hidden rounded-xl border bg-border text-sm md:grid-cols-3"
            >
                <div class="bg-card p-4">
                    <h2 class="mb-1 text-xs font-medium text-muted-foreground">
                        Kunde
                    </h2>
                    {#if order.customer}
                        <p class="font-medium">{order.customer.name}</p>
                        <a
                            href="mailto:{order.customer.email}"
                            class="text-[#1a6bbf] hover:underline"
                            >{order.customer.email}</a
                        >
                    {:else}
                        <p class="text-muted-foreground">
                            Kunde nicht verfügbar.
                        </p>
                    {/if}
                </div>
                <div class="bg-card p-4">
                    <h2 class="mb-1 text-xs font-medium text-muted-foreground">
                        Lieferadresse
                    </h2>
                    <p>{formatAddress(order.shipping_address)}</p>
                </div>
                <div class="bg-card p-4">
                    <h2 class="mb-1 text-xs font-medium text-muted-foreground">
                        Rechnungsadresse
                    </h2>
                    <p>{formatAddress(order.billing_address)}</p>
                </div>
            </section>
        </div>
    </div>
</div>
