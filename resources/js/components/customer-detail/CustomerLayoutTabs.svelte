<script lang="ts">
    import CustomerAddress from '@/components/customer-detail/CustomerAddress.svelte';
    import CustomerOrdersTable from '@/components/customer-detail/CustomerOrdersTable.svelte';
    import { Badge } from '@/components/ui/badge';
    import * as Tabs from '@/components/ui/tabs';
    import { formatDay } from '@/lib/customerDetail';
    import type { CustomerDetail } from '@/types';

    let { customer }: { customer: CustomerDetail } = $props();
</script>

{#snippet count(n: number)}
    {#if n > 0}
        <span
            class="ml-1.5 rounded-full bg-muted px-1.5 py-0.5 text-xs font-medium tabular-nums"
        >
            {n}
        </span>
    {/if}
{/snippet}

<!-- Variante D: kompakter Kopf, Übersicht / Bestellungen / Adressen in Reitern -->
<div class="flex flex-1 flex-col gap-6 p-4 pb-20">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{customer.name}</h1>
            <a
                href="mailto:{customer.email}"
                class="text-sm text-[#1a6bbf] hover:underline"
                >{customer.email}</a
            >
        </div>
        {#if customer.email_verified_at}
            <Badge variant="secondary">E-Mail verifiziert</Badge>
        {:else}
            <Badge variant="outline">E-Mail nicht verifiziert</Badge>
        {/if}
    </header>

    <Tabs.Root value="uebersicht">
        <Tabs.List>
            <Tabs.Trigger value="uebersicht">Übersicht</Tabs.Trigger>
            <Tabs.Trigger value="bestellungen">
                Bestellungen
                {@render count(customer.orders.length)}
            </Tabs.Trigger>
            <Tabs.Trigger value="adressen">
                Adressen
                {@render count(customer.addresses.length)}
            </Tabs.Trigger>
        </Tabs.List>

        <Tabs.Content value="uebersicht">
            <dl class="mt-6 grid max-w-xl gap-3 text-sm">
                <div class="flex justify-between gap-4 border-b pb-2">
                    <dt class="text-muted-foreground">Name</dt>
                    <dd class="font-medium">{customer.name}</dd>
                </div>
                <div class="flex justify-between gap-4 border-b pb-2">
                    <dt class="text-muted-foreground">E-Mail</dt>
                    <dd>{customer.email}</dd>
                </div>
                <div class="flex justify-between gap-4 border-b pb-2">
                    <dt class="text-muted-foreground">E-Mail verifiziert</dt>
                    <dd>
                        {customer.email_verified_at
                            ? formatDay(customer.email_verified_at)
                            : 'Nein'}
                    </dd>
                </div>
                <div class="flex justify-between gap-4 border-b pb-2">
                    <dt class="text-muted-foreground">Registriert</dt>
                    <dd>{formatDay(customer.created_at)}</dd>
                </div>
                <div class="flex justify-between gap-4 border-b pb-2">
                    <dt class="text-muted-foreground">Bestellungen</dt>
                    <dd class="font-medium">{customer.orders.length}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Adressen</dt>
                    <dd class="font-medium">{customer.addresses.length}</dd>
                </div>
            </dl>
        </Tabs.Content>

        <Tabs.Content value="bestellungen">
            <div class="mt-6 rounded-xl border p-2">
                <CustomerOrdersTable orders={customer.orders} />
            </div>
        </Tabs.Content>

        <Tabs.Content value="adressen">
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {#each customer.addresses as address (address.id)}
                    <CustomerAddress {address} />
                {:else}
                    <p class="text-sm text-muted-foreground">
                        Keine Adressen hinterlegt.
                    </p>
                {/each}
            </div>
        </Tabs.Content>
    </Tabs.Root>
</div>
