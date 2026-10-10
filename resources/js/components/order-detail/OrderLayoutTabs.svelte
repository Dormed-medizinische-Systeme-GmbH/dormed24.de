<script lang="ts">
    import OrderItemThumb from '@/components/order-detail/OrderItemThumb.svelte';
    import OrderStatusPill from '@/components/order-detail/OrderStatusPill.svelte';
    import OrderTotals from '@/components/order-detail/OrderTotals.svelte';
    import * as Tabs from '@/components/ui/tabs';
    import { formatPrice } from '@/lib/currency';
    import {
        formatAddress,
        formatOrderedAt,
        paymentStatusLabel,
    } from '@/lib/orderDetail';
    import type { OrderDetail } from '@/types';

    let { order }: { order: OrderDetail } = $props();

    const orderedAt = $derived(formatOrderedAt(order.created_at));
    const lastPayment = $derived(order.payments.at(-1));
</script>

<!-- Variante D: Kennzahlen oben, Details in Reitern -->
<div class="flex flex-1 flex-col gap-6 p-4 pb-20">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Bestellung #{order.id}</h1>
            <p class="text-sm text-muted-foreground">vom {orderedAt}</p>
        </div>
        <OrderStatusPill status={order.status} size="lg" />
    </div>

    <dl
        class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border bg-border lg:grid-cols-4"
    >
        <div class="bg-card p-5">
            <dt class="text-xs text-muted-foreground">Gesamtsumme</dt>
            <dd class="mt-1 text-2xl font-semibold tabular-nums">
                {formatPrice(order.total_amount)}
            </dd>
        </div>
        <div class="bg-card p-5">
            <dt class="text-xs text-muted-foreground">Positionen</dt>
            <dd class="mt-1 text-2xl font-semibold tabular-nums">
                {order.items.length}
            </dd>
        </div>
        <div class="bg-card p-5">
            <dt class="text-xs text-muted-foreground">Versandkosten</dt>
            <dd class="mt-1 text-2xl font-semibold tabular-nums">
                {formatPrice(order.shipping_amount)}
            </dd>
        </div>
        <div class="bg-card p-5">
            <dt class="text-xs text-muted-foreground">Letzte Transaktion</dt>
            <dd class="mt-1 text-base font-semibold">
                {lastPayment ? paymentStatusLabel(lastPayment.status) : '—'}
            </dd>
        </div>
    </dl>

    <Tabs.Root value="positionen">
        <Tabs.List>
            <Tabs.Trigger value="positionen">Positionen</Tabs.Trigger>
            <Tabs.Trigger value="zahlung">Zahlung</Tabs.Trigger>
            <Tabs.Trigger value="kunde">Kunde &amp; Adressen</Tabs.Trigger>
        </Tabs.List>

        <Tabs.Content value="positionen">
            <div
                class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]"
            >
                <div class="overflow-hidden rounded-xl border">
                    {#each order.items as item (item.id)}
                        <div
                            class="flex items-center gap-3 border-b px-4 py-3 last:border-b-0"
                        >
                            <OrderItemThumb {item} />
                            <div class="min-w-0 flex-1">
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
                                        Number(item.unit_price) * item.quantity
                                    ).toFixed(2),
                                )}*
                            </span>
                        </div>
                    {:else}
                        <p
                            class="p-5 text-center text-sm text-muted-foreground"
                        >
                            Keine Positionen vorhanden.
                        </p>
                    {/each}
                </div>
                <div class="rounded-xl border p-5">
                    <OrderTotals summary={order.summary} />
                </div>
            </div>
        </Tabs.Content>

        <Tabs.Content value="zahlung">
            <div class="mt-6 flex max-w-xl flex-col gap-3 text-sm">
                {#each order.payments as payment (payment.id)}
                    <div class="rounded-xl border p-4">
                        <p class="font-medium">
                            {paymentStatusLabel(payment.status)}
                        </p>
                        {#if payment.payer_email}
                            <p class="text-muted-foreground">
                                Zahler: {payment.payer_name ??
                                    payment.payer_email}
                            </p>
                        {/if}
                        {#if payment.paypal_order_id}
                            <p class="font-mono text-xs text-muted-foreground">
                                {payment.paypal_order_id}
                            </p>
                        {/if}
                    </div>
                {:else}
                    <p class="text-muted-foreground">Keine Transaktionen.</p>
                {/each}
            </div>
        </Tabs.Content>

        <Tabs.Content value="kunde">
            <div class="mt-6 grid gap-6 text-sm md:grid-cols-3">
                <div class="rounded-xl border p-5">
                    <h2 class="mb-2 font-semibold">Kunde</h2>
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
                <div class="rounded-xl border p-5">
                    <h2 class="mb-2 font-semibold">Lieferadresse</h2>
                    <p class="text-muted-foreground">
                        {formatAddress(order.shipping_address)}
                    </p>
                </div>
                <div class="rounded-xl border p-5">
                    <h2 class="mb-2 font-semibold">Rechnungsadresse</h2>
                    <p class="text-muted-foreground">
                        {formatAddress(order.billing_address)}
                    </p>
                </div>
            </div>
        </Tabs.Content>
    </Tabs.Root>
</div>
