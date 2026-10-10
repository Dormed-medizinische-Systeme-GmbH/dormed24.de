<script lang="ts">
    import CustomerAddress from '@/components/customer-detail/CustomerAddress.svelte';
    import CustomerOrdersTable from '@/components/customer-detail/CustomerOrdersTable.svelte';
    import { Avatar, AvatarFallback } from '@/components/ui/avatar';
    import { Badge } from '@/components/ui/badge';
    import { formatPrice } from '@/lib/currency';
    import { customerStats, formatDay } from '@/lib/customerDetail';
    import { getInitials } from '@/lib/initials';
    import type { CustomerDetail } from '@/types';

    let { customer }: { customer: CustomerDetail } = $props();

    const stats = $derived(customerStats(customer));
</script>

<!-- Variante B: Profil-Kopf, Kennzahlen-Leiste, darunter Bestellungen und Adressen -->
<div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-8 p-4 pb-20">
    <header class="flex flex-wrap items-center gap-4">
        <Avatar class="size-16">
            <AvatarFallback class="text-xl font-semibold">
                {getInitials(customer.name)}
            </AvatarFallback>
        </Avatar>
        <div class="min-w-0 flex-1">
            <h1 class="truncate text-2xl font-semibold">{customer.name}</h1>
            <a
                href="mailto:{customer.email}"
                class="text-sm text-[#1a6bbf] hover:underline"
                >{customer.email}</a
            >
        </div>
        <div class="flex flex-wrap items-center gap-2 text-sm">
            {#if customer.email_verified_at}
                <Badge variant="secondary"
                    >E-Mail verifiziert {formatDay(
                        customer.email_verified_at,
                    )}</Badge
                >
            {:else}
                <Badge variant="outline">E-Mail nicht verifiziert</Badge>
            {/if}
            <span class="text-muted-foreground"
                >Kunde seit {formatDay(customer.created_at)}</span
            >
        </div>
    </header>

    <dl
        class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border bg-border lg:grid-cols-4"
    >
        <div class="bg-card p-5">
            <dt class="text-xs text-muted-foreground">Bestellungen</dt>
            <dd class="mt-1 text-2xl font-semibold tabular-nums">
                {stats.orderCount}
            </dd>
        </div>
        <div class="bg-card p-5">
            <dt class="text-xs text-muted-foreground">
                Summe aller Bestellungen
            </dt>
            <dd class="mt-1 text-2xl font-semibold tabular-nums">
                {formatPrice(stats.ordersTotal)}
            </dd>
        </div>
        <div class="bg-card p-5">
            <dt class="text-xs text-muted-foreground">Ø Bestellwert</dt>
            <dd class="mt-1 text-2xl font-semibold tabular-nums">
                {stats.averageOrderValue === null
                    ? '—'
                    : formatPrice(stats.averageOrderValue)}
            </dd>
        </div>
        <div class="bg-card p-5">
            <dt class="text-xs text-muted-foreground">Letzte Bestellung</dt>
            <dd class="mt-1 text-2xl font-semibold tabular-nums">
                {stats.lastOrderAt ? formatDay(stats.lastOrderAt) : '—'}
            </dd>
        </div>
    </dl>

    <section class="flex flex-col gap-3">
        <h2 class="text-base font-semibold">Letzte Bestellungen</h2>
        <div class="rounded-xl border p-2">
            <CustomerOrdersTable orders={customer.orders} />
        </div>
    </section>

    {#if customer.addresses.length > 0}
        <section class="flex flex-col gap-3">
            <h2 class="text-base font-semibold">
                Adressen
                <span class="font-normal text-muted-foreground"
                    >({customer.addresses.length})</span
                >
            </h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {#each customer.addresses as address (address.id)}
                    <CustomerAddress {address} />
                {/each}
            </div>
        </section>
    {/if}
</div>
