<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'Bestellungen', href: '/admin/orders' },
            { title: 'Bestelldetail' },
        ],
    };
</script>

<script lang="ts">
    import { router, setLayoutProps } from '@inertiajs/svelte';
    import { Loader2 } from '@lucide/svelte';
    import Package from '@lucide/svelte/icons/package';
    import { toast } from 'svelte-sonner';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { Separator } from '@/components/ui/separator';
    import { formatPrice } from '@/lib/currency';
    import { fetchJson } from '@/lib/http';
    import { orderStatusBadgeClass, orderStatusLabel } from '@/lib/orderStatus';
    import {
        confirmPayment as confirmPaymentRoute,
        refund as refundRoute,
    } from '@/routes/admin/orders';

    type OrderItem = {
        id: number;
        product_name: string;
        unit_price: string;
        quantity: number;
        image_url: string | null;
    };

    type OrderSummary = {
        subtotal: string;
        shipping_total: string;
        vat_rate: number;
        vat_amount: string;
        total: string;
    };

    type Payment = {
        id: number;
        paypal_order_id: string | null;
        status: string;
        amount: string;
        currency: string;
        payer_email: string | null;
        payer_name: string | null;
        created_at: string;
    };

    type AddressSnapshot = Record<string, string> | null;

    type OrderDetail = {
        id: number;
        status: string;
        payment_method: string | null;
        total_amount: string;
        shipping_amount: string;
        shipping_address: AddressSnapshot;
        billing_address: AddressSnapshot;
        created_at: string;
        updated_at: string;
        customer: { id: number; name: string; email: string } | null;
        items: OrderItem[];
        payments: Payment[];
        summary: OrderSummary;
    };

    let { order }: { order: OrderDetail } = $props();

    const paymentStatusLabels: Record<string, string> = {
        CREATED: 'Erstellt',
        APPROVED: 'Bestätigt',
        COMPLETED: 'Abgeschlossen',
        FAILED: 'Fehlgeschlagen',
        REFUNDED: 'Rückerstattet',
    };

    const orderedAt = $derived(
        new Date(order.created_at).toLocaleDateString('de-DE', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }),
    );

    let notifyCustomer = $state(false);
    let confirmingPayment = $state(false);
    let refunding = $state(false);

    const canConfirmPayment = $derived(
        order.payment_method === 'invoice' && order.status === 'pending',
    );
    const canRefund = $derived(
        order.payments.some((p) => p.status === 'COMPLETED'),
    );

    async function confirmPayment() {
        confirmingPayment = true;

        try {
            const { ok, data } = await fetchJson<{ message?: string }>(
                confirmPaymentRoute.url(order.id),
                {
                    method: 'PATCH',
                    body: { notify: notifyCustomer },
                },
            );

            if (ok) {
                toast.success(data.message ?? 'Zahlungseingang bestätigt.');
                router.reload({ only: ['order'] });
            } else {
                toast.error(
                    data.message ??
                        'Zahlungseingang konnte nicht bestätigt werden.',
                );
            }
        } catch {
            toast.error('Verbindungsfehler.');
        } finally {
            confirmingPayment = false;
        }
    }

    async function refund() {
        if (!confirm('Diese PayPal-Zahlung wirklich vollständig erstatten?')) {
            return;
        }

        refunding = true;

        try {
            const { ok, data } = await fetchJson<{ message?: string }>(
                refundRoute.url(order.id),
                {
                    method: 'POST',
                },
            );

            if (ok) {
                toast.success(data.message ?? 'Zahlung erstattet.');
                router.reload({ only: ['order'] });
            } else {
                toast.error(data.message ?? 'Erstattung fehlgeschlagen.');
            }
        } catch {
            toast.error('Verbindungsfehler.');
        } finally {
            refunding = false;
        }
    }

    function formatAddress(a: AddressSnapshot): string {
        if (!a) {
            return '—';
        }

        const lines = [
            a.company,
            (a.salutation ? a.salutation + ' ' : '') +
                (a.first_name ?? '') +
                ' ' +
                (a.last_name ?? ''),
            (a.street ?? '') + ' ' + (a.house_number ?? ''),
            a.address_line2,
            (a.zip ?? '') + ' ' + (a.city ?? ''),
        ].filter(Boolean);

        return lines.join(', ') || '—';
    }

    // Bestellaktionen rechts im Admin-Header.
    $effect(() => {
        setLayoutProps({ actions: headerActions });
    });
</script>

{#snippet headerActions()}
    {#if canConfirmPayment}
        <label class="flex items-center gap-2 text-sm text-muted-foreground">
            <input
                type="checkbox"
                bind:checked={notifyCustomer}
                class="accent-primary"
            />
            Kunde benachrichtigen
        </label>
        <Button onclick={confirmPayment} disabled={confirmingPayment}>
            {#if confirmingPayment}<Loader2 class="size-4 animate-spin" />{/if}
            Zahlungseingang bestätigen
        </Button>
    {/if}
    {#if canRefund}
        <Button variant="destructive" onclick={refund} disabled={refunding}>
            {#if refunding}<Loader2 class="size-4 animate-spin" />{/if}
            Zahlung erstatten
        </Button>
    {/if}
{/snippet}

<AppHead title="Bestellung #{order.id} — Admin" />

<div class="flex flex-1 flex-col gap-6 p-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold">Bestellung #{order.id}</h1>
            <p class="text-sm text-muted-foreground">
                vom {new Date(order.created_at).toLocaleDateString('de-DE', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                })}
            </p>
        </div>
        <span
            class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium {orderStatusBadgeClass(
                order.status,
            )}"
        >
            {orderStatusLabel(order.status)}
        </span>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <!-- Kundendaten -->
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
                    <span
                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {orderStatusBadgeClass(
                            order.status,
                        )}"
                    >
                        {orderStatusLabel(order.status)}
                    </span>
                </div>
            </CardContent>
        </Card>

        <!-- Zahlungsinformationen -->
        <Card>
            <CardHeader>
                <CardTitle>Zahlung</CardTitle>
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
                                <span
                                    >{paymentStatusLabels[payment.status] ??
                                        payment.status}</span
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

    <!-- Adressen -->
    <div class="grid gap-6 md:grid-cols-2">
        <Card>
            <CardHeader>
                <CardTitle>Lieferadresse</CardTitle>
            </CardHeader>
            <CardContent class="whitespace-pre-wrap text-sm">
                {#if order.shipping_address}
                    <p class="text-muted-foreground">
                        {formatAddress(order.shipping_address)}
                    </p>
                {:else}
                    <p class="text-muted-foreground">—</p>
                {/if}
            </CardContent>
        </Card>
        <Card>
            <CardHeader>
                <CardTitle>Rechnungsadresse</CardTitle>
            </CardHeader>
            <CardContent class="whitespace-pre-wrap text-sm">
                {#if order.billing_address}
                    <p class="text-muted-foreground">
                        {formatAddress(order.billing_address)}
                    </p>
                {:else}
                    <p class="text-muted-foreground">—</p>
                {/if}
            </CardContent>
        </Card>
    </div>

    <!-- Positionen (identisch zur Kundenansicht: UserSettingsDialog.svelte) -->
    <div class="overflow-hidden rounded-3xl border bg-white shadow-sm">
        {#each order.items as item (item.id)}
            <div
                class="flex items-center gap-3 border-b px-4 py-3 last:border-b-0"
            >
                <div
                    class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded bg-gray-100"
                >
                    {#if item.image_url}
                        <img
                            src={item.image_url}
                            alt={item.product_name}
                            class="size-full object-cover object-center"
                        />
                    {:else}
                        <Package class="size-4 text-gray-300" />
                    {/if}
                </div>
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
