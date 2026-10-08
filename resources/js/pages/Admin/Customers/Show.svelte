<script module lang="ts">
    import AppLayout from '@/layouts/app/AppSidebarLayout.svelte';

    export const layout = AppLayout;
    export const breadcrumbs = [
        { title: 'Kunden', href: '/admin/customers' },
        { title: 'Kundendetail' },
    ];
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import {
        Table,
        TableBody,
        TableCell,
        TableHead,
        TableHeader,
        TableRow,
    } from '@/components/ui/table';
    import { formatPrice } from '@/lib/currency';
    import { orderStatusBadgeClass, orderStatusLabel } from '@/lib/status';

    type Address = {
        id: number;
        type: string;
        company: string | null;
        salutation: string | null;
        first_name: string;
        last_name: string;
        street: string;
        house_number: string;
        address_line2: string | null;
        zip: string;
        city: string;
        country: string;
        phone: string | null;
        is_default: boolean;
    };

    type OrderItem = {
        product_name: string;
        unit_price: string;
        quantity: number;
    };

    type Order = {
        id: number;
        status: string;
        total_amount: string;
        created_at: string;
        items: OrderItem[];
    };

    type CustomerDetail = {
        id: number;
        name: string;
        email: string;
        email_verified_at: string | null;
        created_at: string;
        addresses: Address[];
        orders: Order[];
    };

    let { customer }: { customer: CustomerDetail } = $props();

    const typeLabels: Record<string, string> = {
        shipping: 'Lieferadresse',
        billing: 'Rechnungsadresse',
        both: 'Liefer- & Rechnungsadresse',
    };
</script>

<AppHead title="{customer.name} — Kunden — Admin" />

<div class="flex flex-1 flex-col gap-6 p-4">
    <!-- Stammdaten -->
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
                            ? new Date(
                                  customer.email_verified_at,
                              ).toLocaleDateString('de-DE')
                            : 'Nein'}</span
                    >
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Registriert</span>
                    <span
                        >{new Date(customer.created_at).toLocaleDateString(
                            'de-DE',
                        )}</span
                    >
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

    <!-- Adressen -->
    {#if customer.addresses.length > 0}
        <Card>
            <CardHeader>
                <CardTitle>Adressen</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 sm:grid-cols-2">
                    {#each customer.addresses as addr (addr.id)}
                        <div class="rounded-lg border bg-gray-50 p-4 text-sm">
                            <div class="mb-2 flex items-center gap-2">
                                <span
                                    class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                                >
                                    {typeLabels[addr.type] ?? addr.type}
                                </span>
                                {#if addr.is_default}
                                    <span
                                        class="rounded-full bg-[#1a6bbf]/10 px-2 py-0.5 text-xs text-[#1a6bbf]"
                                        >Standard</span
                                    >
                                {/if}
                            </div>
                            {#if addr.company}<p class="font-medium">
                                    {addr.company}
                                </p>{/if}
                            <p>
                                {addr.first_name + ' ' + addr.last_name}
                            </p>
                            <p>{addr.street} {addr.house_number}</p>
                            {#if addr.address_line2}<p>
                                    {addr.address_line2}
                                </p>{/if}
                            <p>{addr.zip} {addr.city}</p>
                            {#if addr.phone}<p
                                    class="mt-1 text-muted-foreground"
                                >
                                    {addr.phone}
                                </p>{/if}
                        </div>
                    {/each}
                </div>
            </CardContent>
        </Card>
    {/if}

    <!-- Bestellungen -->
    <Card>
        <CardHeader>
            <CardTitle>Letzte Bestellungen</CardTitle>
        </CardHeader>
        <CardContent>
            {#if customer.orders.length === 0}
                <p class="py-4 text-center text-sm text-muted-foreground">
                    Keine Bestellungen vorhanden.
                </p>
            {:else}
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nr.</TableHead>
                            <TableHead>Datum</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Positionen</TableHead>
                            <TableHead class="text-right">Gesamt</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {#each customer.orders as order (order.id)}
                            <TableRow>
                                <TableCell class="font-medium"
                                    >#{order.id}</TableCell
                                >
                                <TableCell
                                    >{new Date(
                                        order.created_at,
                                    ).toLocaleDateString('de-DE')}</TableCell
                                >
                                <TableCell>
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {orderStatusBadgeClass(
                                            order.status,
                                        )}"
                                    >
                                        {orderStatusLabel(order.status)}
                                    </span>
                                </TableCell>
                                <TableCell>{order.items.length}</TableCell>
                                <TableCell class="text-right font-semibold"
                                    >{formatPrice(
                                        order.total_amount,
                                    )}</TableCell
                                >
                            </TableRow>
                        {/each}
                    </TableBody>
                </Table>
            {/if}
        </CardContent>
    </Card>
</div>
