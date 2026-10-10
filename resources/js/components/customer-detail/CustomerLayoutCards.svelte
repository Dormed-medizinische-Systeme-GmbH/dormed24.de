<script lang="ts">
    import CustomerAddress from '@/components/customer-detail/CustomerAddress.svelte';
    import CustomerOrdersTable from '@/components/customer-detail/CustomerOrdersTable.svelte';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { formatDay } from '@/lib/customerDetail';
    import type { CustomerDetail } from '@/types';

    let { customer }: { customer: CustomerDetail } = $props();
</script>

<!-- Variante A: Karten (bisheriges Layout) -->
<div class="flex flex-1 flex-col gap-6 p-4 pb-20">
    <div class="grid gap-6 md:grid-cols-2">
        <Card>
            <CardHeader>
                <CardTitle>Stammdaten</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Name</span>
                    <span class="font-medium">{customer.name}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">E-Mail</span>
                    <a
                        href="mailto:{customer.email}"
                        class="text-[#1a6bbf] hover:underline"
                        >{customer.email}</a
                    >
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">E-Mail verifiziert</span
                    >
                    <span
                        >{customer.email_verified_at
                            ? formatDay(customer.email_verified_at)
                            : 'Nein'}</span
                    >
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Registriert</span>
                    <span>{formatDay(customer.created_at)}</span>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Statistiken</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Bestellungen</span>
                    <span class="font-medium">{customer.orders.length}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Adressen</span>
                    <span class="font-medium">{customer.addresses.length}</span>
                </div>
            </CardContent>
        </Card>
    </div>

    {#if customer.addresses.length > 0}
        <Card>
            <CardHeader>
                <CardTitle>Adressen</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-2">
                    {#each customer.addresses as address (address.id)}
                        <CustomerAddress {address} />
                    {/each}
                </div>
            </CardContent>
        </Card>
    {/if}

    <Card>
        <CardHeader>
            <CardTitle>Letzte Bestellungen</CardTitle>
        </CardHeader>
        <CardContent>
            <CustomerOrdersTable orders={customer.orders} />
        </CardContent>
    </Card>
</div>
