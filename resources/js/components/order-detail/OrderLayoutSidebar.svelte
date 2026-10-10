<script lang="ts">
    import OrderItemThumb from '@/components/order-detail/OrderItemThumb.svelte';
    import OrderStatusPill from '@/components/order-detail/OrderStatusPill.svelte';
    import OrderTotals from '@/components/order-detail/OrderTotals.svelte';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
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

<!-- Variante C: Positionen im Fokus, Status/Kunde/Zahlung/Adressen in der Seitenleiste -->
<div class="flex flex-1 flex-col gap-6 p-4 pb-20">
    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_21rem]">
        <div class="flex min-w-0 flex-col gap-6">
            <Card>
                <CardHeader>
                    <CardTitle>Positionen</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col">
                    {#each order.items as item (item.id)}
                        <div
                            class="flex items-center gap-4 border-b py-4 first:pt-0 last:border-b-0"
                        >
                            <OrderItemThumb {item} class="size-16" />
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{item.product_name}</p>
                                <p class="text-sm text-muted-foreground">
                                    {item.quantity} × {formatPrice(
                                        item.unit_price,
                                    )}*
                                </p>
                            </div>
                            <span class="font-semibold tabular-nums">
                                {formatPrice(
                                    (
                                        Number(item.unit_price) * item.quantity
                                    ).toFixed(2),
                                )}*
                            </span>
                        </div>
                    {:else}
                        <p
                            class="py-4 text-center text-sm text-muted-foreground"
                        >
                            Keine Positionen vorhanden.
                        </p>
                    {/each}
                </CardContent>
            </Card>

            <Card>
                <CardContent>
                    <OrderTotals summary={order.summary} />
                </CardContent>
            </Card>
        </div>

        <aside class="flex min-w-0 flex-col gap-4 lg:sticky lg:top-0">
            <Card class="gap-4 py-5">
                <CardContent class="flex items-start justify-between gap-3">
                    <div>
                        <h1 class="text-xl font-semibold">
                            Bestellung #{order.id}
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            vom {orderedAt}
                        </p>
                    </div>
                    <OrderStatusPill status={order.status} size="sm" />
                </CardContent>
            </Card>

            <Card class="gap-3 py-5">
                <CardHeader>
                    <CardTitle class="text-sm">Kunde</CardTitle>
                </CardHeader>
                <CardContent class="text-sm">
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
                </CardContent>
            </Card>

            <Card class="gap-3 py-5">
                <CardHeader>
                    <CardTitle class="text-sm">Zahlung</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Gesamtsumme</span>
                        <span class="font-semibold"
                            >{formatPrice(order.total_amount)}</span
                        >
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Versandkosten</span>
                        <span>{formatPrice(order.shipping_amount)}</span>
                    </div>
                    {#each order.payments as payment (payment.id)}
                        <div class="rounded-md border p-2 text-xs">
                            <p class="font-medium">
                                {paymentStatusLabel(payment.status)}
                            </p>
                            {#if payment.payer_email}
                                <p class="text-muted-foreground">
                                    {payment.payer_name ?? payment.payer_email}
                                </p>
                            {/if}
                            {#if payment.paypal_order_id}
                                <p class="font-mono text-muted-foreground">
                                    {payment.paypal_order_id}
                                </p>
                            {/if}
                        </div>
                    {/each}
                </CardContent>
            </Card>

            <Card class="gap-3 py-5">
                <CardHeader>
                    <CardTitle class="text-sm">Lieferadresse</CardTitle>
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    {formatAddress(order.shipping_address)}
                </CardContent>
            </Card>

            <Card class="gap-3 py-5">
                <CardHeader>
                    <CardTitle class="text-sm">Rechnungsadresse</CardTitle>
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    {formatAddress(order.billing_address)}
                </CardContent>
            </Card>
        </aside>
    </div>
</div>
