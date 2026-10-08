<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Hersteller', href: '/admin/manufacturers' }],
    };
</script>

<script lang="ts">
    import { router, setLayoutProps } from '@inertiajs/svelte';
    import Plus from '@lucide/svelte/icons/plus';
    import type { ColumnDef } from '@tanstack/table-core';
    import * as AdminManufacturerController from '@/actions/App/Http/Controllers/Admin/ManufacturerController';
    import AdminDataTable from '@/components/AdminDataTable.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import RowActions from '@/components/RowActions.svelte';
    import { Button } from '@/components/ui/button';
    import { renderComponent } from '@/components/ui/data-table';

    type Manufacturer = {
        id: number;
        name: string;
        country: string | null;
        contact_email: string | null;
    };

    let { manufacturers }: { manufacturers: Manufacturer[] } = $props();

    const columns: ColumnDef<Manufacturer>[] = [
        { accessorKey: 'name', header: 'Name' },
        {
            accessorKey: 'country',
            header: 'Land',
            cell: ({ row }) => row.original.country ?? '—',
        },
        {
            accessorKey: 'contact_email',
            header: 'Kontakt',
            cell: ({ row }) => row.original.contact_email ?? '—',
        },
        {
            id: 'actions',
            header: '',
            cell: ({ row }) =>
                renderComponent(RowActions, {
                    editHref: AdminManufacturerController.edit.url(
                        row.original.id,
                    ),
                    destroyHref: AdminManufacturerController.destroy.url(
                        row.original.id,
                    ),
                    confirmMessage: `Hersteller „${row.original.name}“ wirklich löschen?`,
                }),
            enableSorting: false,
            enableHiding: false,
        },
    ];

    // Anzahl + „Hersteller hinzufügen“ rechts im Admin-Header.
    $effect(() => {
        setLayoutProps({ actions: headerActions });
    });
</script>

{#snippet headerActions()}
    <span class="text-sm text-muted-foreground"
        >{manufacturers.length} gesamt</span
    >
    <Button
        onclick={() => router.visit(AdminManufacturerController.create.url())}
    >
        <Plus class="size-4" />
        Hersteller hinzufügen
    </Button>
{/snippet}

<AppHead title="Hersteller — Admin" />

<div class="flex h-full flex-1 flex-col gap-4 p-4">
    <h1 class="sr-only">Hersteller</h1>

    <AdminDataTable
        data={manufacturers}
        {columns}
        searchFields={(manufacturer) => [
            manufacturer.name,
            manufacturer.country,
            manufacturer.contact_email,
        ]}
        searchPlaceholder="Name, Land oder Kontakt filtern…"
        emptyText="Keine Hersteller vorhanden."
        noMatchText="Kein Hersteller passt zur Suche."
    />
</div>
