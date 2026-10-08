<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Bestellungen', href: '/admin/orders' }],
    };
</script>

<script lang="ts">
    import { page, setLayoutProps } from '@inertiajs/svelte';
    import type { ColumnDef } from '@tanstack/table-core';
    import * as AdminOrderController from '@/actions/App/Http/Controllers/Admin/OrderController';
    import AdminDataTable from '@/components/AdminDataTable.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import StatusBadge from '@/components/StatusBadge.svelte';
    import { renderComponent } from '@/components/ui/data-table';
    import { formatPrice } from '@/lib/currency';
    import CustomerCell from './CustomerCell.svelte';

    type Order = {
        id: number;
        status: string;
        total_amount: string;
        customer: { id: number; name: string; email: string } | null;
        created_at: string;
    };

    let { orders }: { orders: Order[] } = $props();

    const formatDate = (date: string) =>
        new Date(date).toLocaleDateString('de-DE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });

    const columns: ColumnDef<Order>[] = [
        {
            accessorKey: 'created_at',
            header: 'Datum',
            cell: ({ row }) => formatDate(row.original.created_at),
        },
        {
            id: 'customer',
            header: 'Kunde',
            accessorFn: (row) => row.customer?.name ?? '',
            cell: ({ row }) =>
                renderComponent(CustomerCell, {
                    customer: row.original.customer,
                }),
        },
        {
            accessorKey: 'total_amount',
            header: 'Gesamt',
            sortingFn: (a, b) =>
                Number(a.original.total_amount) -
                Number(b.original.total_amount),
            cell: ({ row }) => formatPrice(row.original.total_amount),
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) =>
                renderComponent(StatusBadge, {
                    status: row.original.status,
                    statuses: page.props.orderStatuses,
                }),
        },
    ];

    // Anzahl rechts im Admin-Header.
    $effect(() => {
        setLayoutProps({ actions: headerActions });
    });
</script>

{#snippet headerActions()}
    <span class="text-sm text-muted-foreground">{orders.length} gesamt</span>
{/snippet}

<AppHead title="Bestellungen — Admin" />

<div class="flex h-full flex-1 flex-col gap-4 p-4">
    <h1 class="sr-only">Bestellungen</h1>

    <AdminDataTable
        data={orders}
        {columns}
        searchFields={(order) => [
            String(order.id),
            order.customer?.name,
            order.customer?.email,
        ]}
        searchPlaceholder="Kunde, E-Mail oder Bestellnummer filtern…"
        emptyText="Keine Bestellungen vorhanden."
        noMatchText="Keine Bestellung passt zur Suche."
        rowHref={(order) => AdminOrderController.show.url(order.id)}
        rowLabel={(order) =>
            `Bestellung ${formatDate(order.created_at)} öffnen`}
    />
</div>
