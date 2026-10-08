<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Newsletter', href: '/admin/newsletters' }],
    };
</script>

<script lang="ts">
    import { router, setLayoutProps } from '@inertiajs/svelte';
    import LayoutTemplate from '@lucide/svelte/icons/layout-template';
    import Plus from '@lucide/svelte/icons/plus';
    import type { ColumnDef } from '@tanstack/table-core';
    import * as NewsletterController from '@/actions/App/Http/Controllers/Admin/NewsletterController';
    import AdminDataTable from '@/components/AdminDataTable.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import RowActions from '@/components/RowActions.svelte';
    import StatusBadge from '@/components/StatusBadge.svelte';
    import TableCellLink from '@/components/TableCellLink.svelte';
    import { Button } from '@/components/ui/button';
    import { renderComponent } from '@/components/ui/data-table';
    import type { StatusMap } from '@/lib/status';

    type Newsletter = {
        id: number;
        name: string;
        subject: string;
        status: string;
        updated_at: string;
    };

    let {
        newsletters,
        statuses,
    }: { newsletters: Newsletter[]; statuses: StatusMap } = $props();

    const columns: ColumnDef<Newsletter>[] = [
        {
            accessorKey: 'name',
            header: 'Name',
            cell: ({ row }) =>
                renderComponent(TableCellLink, {
                    href: NewsletterController.edit.url(row.original.id),
                    label: row.original.name,
                }),
        },
        { accessorKey: 'subject', header: 'Betreff' },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) =>
                renderComponent(StatusBadge, {
                    status: row.original.status,
                    statuses,
                }),
        },
        { accessorKey: 'updated_at', header: 'Geändert' },
        {
            id: 'actions',
            header: '',
            cell: ({ row }) =>
                renderComponent(RowActions, {
                    editHref: NewsletterController.edit.url(row.original.id),
                    links: [
                        {
                            href: NewsletterController.content.url(
                                row.original.id,
                            ),
                            label: 'Inhalt bearbeiten',
                            icon: LayoutTemplate,
                        },
                    ],
                    // Verschickte Newsletter bleiben als Beleg erhalten.
                    destroyHref:
                        row.original.status === 'draft'
                            ? NewsletterController.destroy.url(row.original.id)
                            : undefined,
                    confirmMessage: `Newsletter „${row.original.name}“ wirklich löschen?`,
                }),
            enableSorting: false,
            enableHiding: false,
        },
    ];

    let creating = $state(false);

    function create() {
        creating = true;
        router.post(
            NewsletterController.store.url(),
            {},
            { onFinish: () => (creating = false) },
        );
    }

    // Anzahl + „Newsletter anlegen“ rechts im Admin-Header.
    $effect(() => {
        setLayoutProps({ actions: headerActions });
    });
</script>

{#snippet headerActions()}
    <span class="text-sm text-muted-foreground"
        >{newsletters.length} gesamt</span
    >
    <Button onclick={create} disabled={creating}>
        <Plus class="size-4" />
        Newsletter anlegen
    </Button>
{/snippet}

<AppHead title="Newsletter — Admin" />

<div class="flex h-full flex-1 flex-col gap-4 p-4">
    <h1 class="sr-only">Newsletter</h1>

    <AdminDataTable
        data={newsletters}
        {columns}
        searchFields={(newsletter) => [newsletter.name, newsletter.subject]}
        searchPlaceholder="Name oder Betreff filtern…"
        emptyText="Noch kein Newsletter angelegt."
        noMatchText="Kein Newsletter passt zur Suche."
    />
</div>
