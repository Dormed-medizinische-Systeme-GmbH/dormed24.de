<script lang="ts">
    import CustomerAddress from '@/components/customer-detail/CustomerAddress.svelte';
    import OrderStatusPill from '@/components/order-detail/OrderStatusPill.svelte';
    import { Avatar, AvatarFallback } from '@/components/ui/avatar';
    import { Card, CardContent } from '@/components/ui/card';
    import { formatPrice } from '@/lib/currency';
    import { customerStats, formatDay } from '@/lib/customerDetail';
    import { getInitials } from '@/lib/initials';
    import type { CustomerDetail } from '@/types';

    let { customer }: { customer: CustomerDetail } = $props();

    const stats = $derived(customerStats(customer));
</script>

<!-- Variante C: Profil und Adressen als Spalte links, Bestellungen mit Positionen rechts -->
<div
    class="grid items-start gap-6 p-4 pb-20 lg:grid-cols-[20rem_minmax(0,1fr)]"
>
    <aside class="flex min-w-0 flex-col gap-4 lg:sticky lg:top-0">
        <Card>
            <CardContent class="flex flex-col gap-4">
                <div class="flex items-center gap-3">
                    <Avatar class="size-12">
                        <AvatarFallback class="font-semibold">
                            {getInitials(customer.name)}
                        </AvatarFallback>
                    </Avatar>
                    <div class="min-w-0">
                        <h1 class="truncate text-lg font-semibold">
                            {customer.name}
                        </h1>
                        <a
                            href="mailto:{customer.email}"
                            class="block truncate text-sm text-[#1a6bbf] hover:underline"
                            >{customer.email}</a
                        >
                    </div>
                </div>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">
                            E-Mail verifiziert
                        </dt>
                        <dd>
                            {customer.email_verified_at
                                ? formatDay(customer.email_verified_at)
                                : 'Nein'}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Registriert</dt>
                        <dd>{formatDay(customer.created_at)}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Bestellungen</dt>
                        <dd class="font-medium">{stats.orderCount}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Adressen</dt>
                        <dd class="font-medium">{stats.addressCount}</dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        {#each customer.addresses as address (address.id)}
            <CustomerAddress {address} />
        {/each}
    </aside>

    <section class="flex min-w-0 flex-col gap-4">
        <h2 class="text-base font-semibold">Letzte Bestellungen</h2>
        {#each customer.orders as order (order.id)}
            <details class="group rounded-xl border bg-card">
                <summary
                    class="flex cursor-pointer list-none flex-wrap items-center gap-x-6 gap-y-1 p-4"
                >
                    <span class="font-semibold">#{order.id}</span>
                    <span class="text-sm text-muted-foreground"
                        >{formatDay(order.created_at)}</span
                    >
                    <OrderStatusPill status={order.status} size="sm" />
                    <span class="text-sm text-muted-foreground"
                        >{order.items.length}
                        {order.items.length === 1
                            ? 'Position'
                            : 'Positionen'}</span
                    >
                    <span class="ml-auto font-semibold tabular-nums"
                        >{formatPrice(order.total_amount)}</span
                    >
                </summary>
                <ul class="divide-y border-t text-sm">
                    {#each order.items as item, index (index)}
                        <li class="flex justify-between gap-4 px-4 py-2">
                            <span class="min-w-0 truncate"
                                >{item.product_name}</span
                            >
                            <span
                                class="shrink-0 text-muted-foreground tabular-nums"
                                >{item.quantity} × {formatPrice(
                                    item.unit_price,
                                )}</span
                            >
                        </li>
                    {/each}
                </ul>
            </details>
        {:else}
            <p class="py-4 text-center text-sm text-muted-foreground">
                Keine Bestellungen vorhanden.
            </p>
        {/each}
    </section>
</div>
