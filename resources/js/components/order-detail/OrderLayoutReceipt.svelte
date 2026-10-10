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
</script>

<!-- Variante B: Beleg – ein Dokument von oben nach unten -->
<div class="flex flex-1 flex-col p-4 pb-20">
    <article
        class="mx-auto w-full max-w-3xl rounded-xl border bg-card p-6 shadow-sm sm:p-10"
    >
        <header class="flex items-start justify-between gap-4 border-b pb-6">
            <div>
                <p
                    class="text-xs font-medium tracking-widest text-muted-foreground uppercase"
                >
                    Bestellung
                </p>
                <h1 class="text-3xl font-semibold">#{order.id}</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    vom {orderedAt}
                </p>
            </div>
            <OrderStatusPill status={order.status} />
        </header>

        <section class="grid gap-6 border-b py-6 text-sm sm:grid-cols-3">
            <div>
                <h2
                    class="mb-2 text-xs font-medium tracking-widest text-muted-foreground uppercase"
                >
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
                    <p class="text-muted-foreground">Kunde nicht verfügbar.</p>
                {/if}
            </div>
            <div>
                <h2
                    class="mb-2 text-xs font-medium tracking-widest text-muted-foreground uppercase"
                >
                    Lieferadresse
                </h2>
                <p class="text-muted-foreground">
                    {formatAddress(order.shipping_address)}
                </p>
            </div>
            <div>
                <h2
                    class="mb-2 text-xs font-medium tracking-widest text-muted-foreground uppercase"
                >
                    Rechnungsadresse
                </h2>
                <p class="text-muted-foreground">
                    {formatAddress(order.billing_address)}
                </p>
            </div>
        </section>

        <section class="py-6">
            <h2 class="sr-only">Positionen</h2>
            {#if order.items.length > 0}
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b text-left text-xs text-muted-foreground"
                        >
                            <th class="w-10 pb-2 font-medium">Pos.</th>
                            <th class="pb-2 font-medium">Artikel</th>
                            <th class="pb-2 text-right font-medium">Menge</th>
                            <th class="pb-2 text-right font-medium">Einzel</th>
                            <th class="pb-2 text-right font-medium">Summe</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each order.items as item, index (item.id)}
                            <tr class="border-b last:border-b-0">
                                <td class="py-3 text-muted-foreground"
                                    >{index + 1}</td
                                >
                                <td class="py-3 pr-2 font-medium">
                                    <span class="flex items-center gap-3">
                                        <OrderItemThumb {item} class="size-9" />
                                        {item.product_name}
                                    </span>
                                </td>
                                <td class="py-3 text-right tabular-nums"
                                    >{item.quantity}</td
                                >
                                <td
                                    class="py-3 text-right text-muted-foreground tabular-nums"
                                    >{formatPrice(item.unit_price)}*</td
                                >
                                <td
                                    class="py-3 text-right font-semibold tabular-nums"
                                >
                                    {formatPrice(
                                        (
                                            Number(item.unit_price) *
                                            item.quantity
                                        ).toFixed(2),
                                    )}*
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            {:else}
                <p class="py-4 text-center text-sm text-muted-foreground">
                    Keine Positionen vorhanden.
                </p>
            {/if}
            <OrderTotals
                summary={order.summary}
                class="mt-4 ml-auto max-w-xs"
            />
        </section>

        <section class="border-t pt-6 text-sm">
            <h2
                class="mb-3 text-xs font-medium tracking-widest text-muted-foreground uppercase"
            >
                Zahlung
            </h2>
            <dl class="grid max-w-xs gap-1">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Gesamtsumme</dt>
                    <dd class="font-semibold">
                        {formatPrice(order.total_amount)}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Versandkosten</dt>
                    <dd>{formatPrice(order.shipping_amount)}</dd>
                </div>
            </dl>
            {#if order.payments.length > 0}
                <p class="mt-4 mb-1 font-medium text-muted-foreground">
                    Transaktionen
                </p>
                <ul class="divide-y rounded-lg border text-xs">
                    {#each order.payments as payment (payment.id)}
                        <li
                            class="flex flex-wrap items-center gap-x-6 gap-y-1 p-3"
                        >
                            <span class="font-medium"
                                >{paymentStatusLabel(payment.status)}</span
                            >
                            {#if payment.payer_email}
                                <span class="text-muted-foreground"
                                    >{payment.payer_name ??
                                        payment.payer_email}</span
                                >
                            {/if}
                            {#if payment.paypal_order_id}
                                <span class="font-mono text-muted-foreground"
                                    >{payment.paypal_order_id}</span
                                >
                            {/if}
                        </li>
                    {/each}
                </ul>
            {/if}
        </section>
    </article>
</div>
