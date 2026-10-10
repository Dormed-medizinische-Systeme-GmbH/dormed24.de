<script lang="ts">
    import CustomerAddress from '@/components/customer-detail/CustomerAddress.svelte';
    import OrderStatusPill from '@/components/order-detail/OrderStatusPill.svelte';
    import { formatPrice } from '@/lib/currency';
    import {
        customerStats,
        customerTimeline,
        formatDay,
    } from '@/lib/customerDetail';
    import type { CustomerDetail } from '@/types';

    let { customer }: { customer: CustomerDetail } = $props();

    const stats = $derived(customerStats(customer));
    const events = $derived(customerTimeline(customer));
</script>

<!-- Variante E: Aktivitätsverlauf – Registrierung, Bestätigung und Bestellungen als Zeitachse -->
<div
    class="grid items-start gap-8 p-4 pb-20 lg:grid-cols-[minmax(0,1fr)_22rem]"
>
    <section class="min-w-0">
        <div class="mb-6">
            <h1 class="text-xl font-semibold">{customer.name}</h1>
            <a
                href="mailto:{customer.email}"
                class="text-sm text-[#1a6bbf] hover:underline"
                >{customer.email}</a
            >
        </div>

        <ol class="relative ml-2 border-l pl-6">
            {#each events as event (event.key)}
                <li class="relative pb-8 last:pb-0">
                    <span
                        class="absolute top-1.5 -left-[1.9rem] size-3 rounded-full ring-4 ring-background {event.kind ===
                        'order'
                            ? 'bg-primary'
                            : 'bg-muted-foreground/50'}"
                    ></span>
                    <p class="text-xs text-muted-foreground">
                        {formatDay(event.at)}
                    </p>
                    {#if event.kind === 'registered'}
                        <p class="font-medium">Kunde registriert</p>
                    {:else if event.kind === 'verified'}
                        <p class="font-medium">E-Mail-Adresse bestätigt</p>
                    {:else}
                        <div
                            class="mt-1 rounded-xl border bg-card p-4"
                            data-testid="timeline-order"
                        >
                            <div
                                class="flex flex-wrap items-center gap-x-4 gap-y-1"
                            >
                                <span class="font-semibold"
                                    >Bestellung #{event.order.id}</span
                                >
                                <OrderStatusPill
                                    status={event.order.status}
                                    size="sm"
                                />
                                <span class="ml-auto font-semibold tabular-nums"
                                    >{formatPrice(
                                        event.order.total_amount,
                                    )}</span
                                >
                            </div>
                            <ul
                                class="mt-2 space-y-0.5 text-sm text-muted-foreground"
                            >
                                {#each event.order.items as item, index (index)}
                                    <li>
                                        {item.quantity} × {item.product_name}
                                    </li>
                                {/each}
                            </ul>
                        </div>
                    {/if}
                </li>
            {/each}
        </ol>
    </section>

    <aside class="flex min-w-0 flex-col gap-4 lg:sticky lg:top-0">
        <dl
            class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border bg-border"
        >
            <div class="bg-card p-4">
                <dt class="text-xs text-muted-foreground">Bestellungen</dt>
                <dd class="text-xl font-semibold tabular-nums">
                    {stats.orderCount}
                </dd>
            </div>
            <div class="bg-card p-4">
                <dt class="text-xs text-muted-foreground">Ø Bestellwert</dt>
                <dd class="text-xl font-semibold tabular-nums">
                    {stats.averageOrderValue === null
                        ? '—'
                        : formatPrice(stats.averageOrderValue)}
                </dd>
            </div>
        </dl>

        <h2 class="text-sm font-semibold">
            Adressen
            <span class="font-normal text-muted-foreground"
                >({stats.addressCount})</span
            >
        </h2>
        {#each customer.addresses as address (address.id)}
            <CustomerAddress {address} />
        {:else}
            <p class="text-sm text-muted-foreground">
                Keine Adressen hinterlegt.
            </p>
        {/each}
    </aside>
</div>
