<script module lang="ts">
    // Breadcrumb mit der Bestellnummer statt „Bestelldetail“ (Layout-Callback über die Page-Props).
    export const layout = (props: { order: { id: number } }) => ({
        breadcrumbs: [
            { title: 'Bestellungen', href: '/admin/orders' },
            { title: `Bestellung #${props.order.id}` },
        ],
    });
</script>

<script lang="ts">
    import { router, setLayoutProps } from '@inertiajs/svelte';
    import { Loader2 } from '@lucide/svelte';
    import { toast } from 'svelte-sonner';
    import AppHead from '@/components/AppHead.svelte';
    import OrderLayoutCards from '@/components/order-detail/OrderLayoutCards.svelte';
    import OrderLayoutPayment from '@/components/order-detail/OrderLayoutPayment.svelte';
    import OrderLayoutReceipt from '@/components/order-detail/OrderLayoutReceipt.svelte';
    import OrderLayoutSidebar from '@/components/order-detail/OrderLayoutSidebar.svelte';
    import OrderLayoutTabs from '@/components/order-detail/OrderLayoutTabs.svelte';
    import { Button } from '@/components/ui/button';
    import VariantSwitcher from '@/components/VariantSwitcher.svelte';
    import { fetchJson } from '@/lib/http';
    import { ORDER_VARIANTS } from '@/lib/orderDetail';
    import { useLayoutVariant } from '@/lib/useLayoutVariant.svelte';
    import {
        confirmPayment as confirmPaymentRoute,
        refund as refundRoute,
    } from '@/routes/admin/orders';
    import type { OrderDetail } from '@/types';

    let { order }: { order: OrderDetail } = $props();

    // Layout-Variante: Auswahl steht in der URL (?variant=B); ohne Parameter gilt A.
    const layoutVariant = useLayoutVariant(ORDER_VARIANTS);

    let notifyCustomer = $state(false);
    let confirmingPayment = $state(false);
    let refunding = $state(false);

    const canConfirmPayment = $derived(
        order.payment_method === 'invoice' && order.status === 'pending',
    );
    const canRefund = $derived(
        order.payments.some((p) =>
            ['COMPLETED', 'MISMATCH'].includes(p.status),
        ),
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

{#if layoutVariant.current === 'B'}
    <OrderLayoutReceipt {order} />
{:else if layoutVariant.current === 'C'}
    <OrderLayoutSidebar {order} />
{:else if layoutVariant.current === 'D'}
    <OrderLayoutTabs {order} />
{:else if layoutVariant.current === 'E'}
    <OrderLayoutPayment {order} />
{:else}
    <OrderLayoutCards {order} />
{/if}

<VariantSwitcher
    options={ORDER_VARIANTS}
    current={layoutVariant.current}
    onselect={layoutVariant.select}
/>
