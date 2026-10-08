<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Kunden', href: '/admin/customers' }],
    };
</script>

<script lang="ts">
    import { router, setLayoutProps } from '@inertiajs/svelte';
    import type { ColumnDef } from '@tanstack/table-core';
    import * as AdminCustomerController from '@/actions/App/Http/Controllers/Admin/CustomerController';
    import AdminDataTable from '@/components/AdminDataTable.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import TableCellLink from '@/components/TableCellLink.svelte';
    import { renderComponent } from '@/components/ui/data-table';

    type Customer = {
        id: number;
        name: string;
        email: string;
        email_verified_at: string | null;
        created_at: string;
    };

    let {
        customers,
        filters,
    }: { customers: Customer[]; filters: { search: string } } = $props();

    const formatDate = (date: string) =>
        new Date(date).toLocaleDateString('de-DE');

    const columns: ColumnDef<Customer>[] = [
        {
            accessorKey: 'name',
            header: 'Name',
            cell: ({ row }) =>
                renderComponent(TableCellLink, {
                    href: AdminCustomerController.show.url(row.original.id),
                    label: row.original.name,
                }),
        },
        { accessorKey: 'email', header: 'E-Mail' },
        {
            accessorKey: 'email_verified_at',
            header: 'Verifiziert',
            cell: ({ row }) =>
                row.original.email_verified_at
                    ? formatDate(row.original.email_verified_at)
                    : '—',
        },
        {
            accessorKey: 'created_at',
            header: 'Registriert',
            cell: ({ row }) => formatDate(row.original.created_at),
        },
    ];

    // Die Suche läuft auf dem Server (Name oder E-Mail), damit sie sich
    // per Link teilen lässt.
    function search(term: string): void {
        router.get(
            AdminCustomerController.index.url(),
            term === '' ? {} : { search: term },
            { preserveScroll: true, replace: true },
        );
    }

    // Anzahl rechts im Admin-Header.
    $effect(() => {
        setLayoutProps({ actions: headerActions });
    });
</script>

{#snippet headerActions()}
    <span class="text-sm text-muted-foreground">{customers.length} gesamt</span>
{/snippet}

<AppHead title="Kunden — Admin" />

<div class="flex h-full flex-1 flex-col gap-4 p-4">
    <h1 class="sr-only">Kunden</h1>

    <AdminDataTable
        data={customers}
        {columns}
        serverSearch={{ value: filters.search, submit: search }}
        searchPlaceholder="Name oder E-Mail filtern…"
        emptyText={filters.search === ''
            ? 'Keine Kunden vorhanden.'
            : 'Kein Kunde passt zur Suche.'}
    />
</div>
