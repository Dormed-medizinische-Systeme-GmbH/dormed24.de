<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import RateProductButton from '@/components/RateProductButton.svelte';
    import { formatPrice } from '@/lib/currency';
    import { orderStatusBadgeClass, orderStatusLabel } from '@/lib/status';

    type OrderItem = {
        id: number;
        product_id: number | null;
        product_name: string;
        unit_price: string;
        quantity: number;
        image_url: string | null;
        rating: 'rateable' | 'rated' | null;
    };

    type AddressSnapshot = Record<string, string> | null;

    type OrderSummary = {
        subtotal: string;
        shipping_total: string;
        vat_rate: number;
        vat_amount: string;
        total: string;
    };

    type OrderDetail = {
        id: number;
        status: string;
        payment_method: string | null;
        payment_method_label: string | null;
        created_at: string | null;
        updated_at: string | null;
        shipping_address: AddressSnapshot;
        billing_address: AddressSnapshot;
        items: OrderItem[];
        summary: OrderSummary;
        customer_email: string;
    };

    let { order }: { order: OrderDetail } = $props();

    const createdAt = $derived(
        order.created_at ? new Date(order.created_at) : null,
    );
    const orderCode = $derived(`ORD-${String(order.id).padStart(5, '0')}`);
    const isTerminalError = $derived(
        order.status === 'cancelled' || order.status === 'failed',
    );

    function formatAddress(address: AddressSnapshot): string[] {
        if (!address) {
            return ['-'];
        }

        const fullName = [
            address.salutation,
            address.first_name,
            address.last_name,
        ]
            .filter(Boolean)
            .join(' ')
            .trim();

        return [
            address.company ?? '',
            fullName,
            [address.street, address.house_number]
                .filter(Boolean)
                .join(' ')
                .trim(),
            address.address_line2 ?? '',
            [address.zip, address.city].filter(Boolean).join(' ').trim(),
            address.country ?? '',
        ].filter((line) => line !== '');
    }
</script>

<AppHead title={`Bestellung ${orderCode}`} />

<div class="space-y-8">
    <div class="space-y-3">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title={`Bestellung ${orderCode}`}
                description={`Bestellt am ${createdAt ? createdAt.toLocaleDateString('de-DE', { day: 'numeric', month: 'long', year: 'numeric' }) : '-'} · Bestätigung an ${order.customer_email}`}
            />

            <span
                class={`inline-flex w-fit items-center rounded-full px-3 py-1 text-sm font-medium ${orderStatusBadgeClass(order.status)}`}
            >
                {orderStatusLabel(order.status)}
            </span>
        </div>

        {#if isTerminalError}
            <p class="text-sm text-red-600">
                Diese Bestellung wurde nicht regulär abgeschlossen. Der aktuelle
                Status lautet
                <span class="font-medium">{orderStatusLabel(order.status)}</span
                >.
            </p>
        {/if}
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-3xl border bg-white p-5 shadow-sm">
            <h3 class="mb-3 text-lg font-semibold text-gray-950">
                Lieferadresse
            </h3>
            <div class="space-y-1 text-sm text-muted-foreground">
                {#each formatAddress(order.shipping_address) as line (line)}
                    <p>{line}</p>
                {/each}
            </div>
        </div>

        <div class="rounded-3xl border bg-white p-5 shadow-sm">
            <h3 class="mb-3 text-lg font-semibold text-gray-950">
                Rechnungsadresse
            </h3>
            <div class="space-y-1 text-sm text-muted-foreground">
                {#each formatAddress(order.billing_address ?? order.shipping_address) as line (line)}
                    <p>{line}</p>
                {/each}
            </div>
        </div>

        <div class="rounded-3xl border bg-white p-5 shadow-sm">
            <h3 class="mb-3 text-lg font-semibold text-gray-950">Zahlung</h3>
            <div class="space-y-1 text-sm text-muted-foreground">
                <p>{order.payment_method_label ?? '-'}</p>
                <p>Gesamtbetrag: {formatPrice(order.summary.total)}*</p>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-3xl border bg-white shadow-sm">
        {#each order.items as item (item.id)}
            <div
                class="flex flex-col gap-4 border-b px-5 py-5 last:border-b-0 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
            >
                <div class="flex items-start gap-4">
                    <div
                        class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-gray-100"
                    >
                        {#if item.image_url}
                            <img
                                src={item.image_url}
                                alt={item.product_name}
                                class="size-full object-cover object-center"
                            />
                        {:else}
                            <div class="size-8 rounded-full bg-gray-200"></div>
                        {/if}
                    </div>

                    <div class="space-y-1">
                        <p class="font-semibold text-gray-950">
                            {item.product_name}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Menge: {item.quantity}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Einzelpreis: {formatPrice(item.unit_price)}*
                        </p>
                    </div>
                </div>

                <div
                    class="text-left text-lg font-semibold text-gray-950 sm:text-right"
                >
                    {formatPrice(
                        (Number(item.unit_price) * item.quantity).toFixed(2),
                    )}*
                </div>

                {#if item.product_id && item.rating}
                    <div class="w-full sm:pl-20">
                        <RateProductButton
                            productId={item.product_id}
                            productName={item.product_name}
                            rating={item.rating}
                        />
                    </div>
                {/if}
            </div>
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
