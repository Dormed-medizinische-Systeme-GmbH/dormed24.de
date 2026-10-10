<script lang="ts">
    import OrderItemThumb from '@/components/order-detail/OrderItemThumb.svelte';
    import OrderStatusPill from '@/components/order-detail/OrderStatusPill.svelte';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { Separator } from '@/components/ui/separator';
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

<!-- Variante A: Karten (bisheriges Layout) -->
<div class="flex flex-1 flex-col gap-6 p-4 pb-20">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold">Bestellung #{order.id}</h1>
            <p class="text-sm text-muted-foreground">vom {orderedAt}</p>
        </div>
        <OrderStatusPill status={order.status} />
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <Card>
            <CardHeader>
                <CardTitle>Bestellinformationen</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-sm">
                {#if order.customer}
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Name</span>
                        <span class="font-medium">{order.customer.name}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">E-Mail</span>
                        <a
                            href="mailto:{order.customer.email}"
                            class="text-[#1a6bbf] hover:underline"
                            >{order.customer.email}</a
                        >
                    </div>
                {:else}
                    <p class="text-muted-foreground">Kunde nicht verfügbar.</p>
                {/if}
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Bestellzeitpunkt</span>
                    <span>{orderedAt}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground">Status</span>
                    <OrderStatusPill status={order.status} size="sm" />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Zahlungsinformationen</CardTitle>
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
                {#if order.payments.length > 0}
                    <Separator />
                    <p class="font-medium text-muted-foreground">
                        Transaktionen
                    </p>
                    {#each order.payments as payment (payment.id)}
                        <div class="rounded bg-gray-50 p-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Status</span
                                >
                                <span>{paymentStatusLabel(payment.status)}</span
                                >
                            </div>
                            {#if payment.payer_email}
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground"
                                        >Zahler</span
                                    >
                                    <span
                                        >{payment.payer_name ??
                                            payment.payer_email}</span
                                    >
                                </div>
                            {/if}
                            {#if payment.paypal_order_id}
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground"
                                        >PayPal-ID</span
                                    >
                                    <span class="font-mono text-xs"
                                        >{payment.paypal_order_id}</span
                                    >
                                </div>
                            {/if}
                        </div>
                    {/each}
                {/if}
            </CardContent>
        </Card>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <Card>
            <CardHeader>
                <CardTitle>Lieferadresse</CardTitle>
            </CardHeader>
            <CardContent class="text-sm whitespace-pre-wrap">
                <p class="text-muted-foreground">
                    {formatAddress(order.shipping_address)}
                </p>
            </CardContent>
        </Card>
        <Card>
            <CardHeader>
                <CardTitle>Rechnungsadresse</CardTitle>
            </CardHeader>
            <CardContent class="text-sm whitespace-pre-wrap">
                <p class="text-muted-foreground">
                    {formatAddress(order.billing_address)}
                </p>
            </CardContent>
        </Card>
    </div>

    <!-- Positionen (identisch zur Kundenansicht: UserSettingsDialog.svelte) -->
    <div class="overflow-hidden rounded-3xl border bg-white shadow-sm">
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
                        {item.quantity} × {formatPrice(item.unit_price)}*
                    </p>
                </div>
                <span class="text-sm font-semibold">
                    {formatPrice(
                        (Number(item.unit_price) * item.quantity).toFixed(2),
                    )}*
                </span>
            </div>
        {:else}
            <p class="p-5 text-center text-sm text-muted-foreground">
                Keine Positionen vorhanden.
            </p>
        {/each}
    </div>

    <div class="rounded-3xl border bg-white p-6 shadow-sm">
        <div class="space-y-2 text-sm">
            <div class="flex items-center justify-between gap-4">
                <span class="text-muted-foreground">Zwischensumme</span>
                <span class="font-medium text-gray-950"
                    >{formatPrice(order.summary.subtotal)}*</span
                >
            </div>
            <div class="flex items-center justify-between gap-4">
                <span class="text-muted-foreground">Versand</span>
                <span class="font-medium text-gray-950"
                    >{formatPrice(order.summary.shipping_total)}*</span
                >
            </div>
            <div class="flex items-center justify-between gap-4">
                <span class="text-muted-foreground"
                    >MwSt. ({order.summary.vat_rate} %)</span
                >
                <span class="font-medium text-gray-950"
                    >{formatPrice(order.summary.vat_amount)}*</span
                >
            </div>
            <div class="mt-2 border-t pt-4">
                <div class="flex items-center justify-between gap-4">
                    <span class="text-xl font-semibold text-gray-950"
                        >Gesamt</span
                    >
                    <span class="text-xl font-semibold text-gray-950"
                        >{formatPrice(order.summary.total)}*</span
                    >
                </div>
            </div>
        </div>
    </div>
</div>
