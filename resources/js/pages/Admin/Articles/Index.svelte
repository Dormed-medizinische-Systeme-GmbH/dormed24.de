<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Magazin', href: '/admin/articles' }],
    };
</script>

<script lang="ts">
    import { router, setLayoutProps } from '@inertiajs/svelte';
    import Plus from '@lucide/svelte/icons/plus';
    import type { ColumnDef } from '@tanstack/table-core';
    import * as ArticleController from '@/actions/App/Http/Controllers/Admin/ArticleController';
    import AdminDataTable from '@/components/AdminDataTable.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import RowActions from '@/components/RowActions.svelte';
    import StatusBadge from '@/components/StatusBadge.svelte';
    import TableCellLink from '@/components/TableCellLink.svelte';
    import { Button } from '@/components/ui/button';
    import { renderComponent } from '@/components/ui/data-table';
    import { formatDateTime } from '@/lib/date';
    import type { StatusMap } from '@/lib/status';

    type Article = {
        id: number;
        title: string;
        slug: string;
        teaser: string | null;
        category: string;
        status: string;
        published_at: string | null;
        updated_at: string;
    };

    let {
        articles,
        categories,
        statuses,
    }: {
        articles: Article[];
        categories: Record<string, string>;
        statuses: StatusMap;
    } = $props();

    const columns: ColumnDef<Article>[] = [
        {
            accessorKey: 'title',
            header: 'Titel',
            cell: ({ row }) =>
                renderComponent(TableCellLink, {
                    href: ArticleController.edit.url(row.original.id),
                    label: row.original.title,
                }),
        },
        {
            accessorKey: 'slug',
            header: 'Adresse',
            cell: ({ row }) => `/magazin/${row.original.slug}`,
        },
        {
            accessorKey: 'category',
            header: 'Kategorie',
            cell: ({ row }) =>
                categories[row.original.category] ?? row.original.category,
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) =>
                renderComponent(StatusBadge, {
                    status: row.original.status,
                    statuses,
                }),
        },
        {
            accessorKey: 'published_at',
            header: 'Veröffentlicht',
            cell: ({ row }) =>
                row.original.published_at
                    ? formatDateTime(row.original.published_at)
                    : '—',
        },
        {
            accessorKey: 'updated_at',
            header: 'Geändert',
            cell: ({ row }) => formatDateTime(row.original.updated_at),
        },
        {
            id: 'actions',
            header: '',
            cell: ({ row }) =>
                renderComponent(RowActions, {
                    editHref: ArticleController.edit.url(row.original.id),
                    destroyHref: ArticleController.destroy.url(row.original.id),
                    confirmMessage: `Artikel „${row.original.title}“ wirklich löschen?`,
                }),
            enableSorting: false,
            enableHiding: false,
        },
    ];

    // Anzahl + „Artikel anlegen“ rechts im Admin-Header.
    $effect(() => {
        setLayoutProps({ actions: headerActions });
    });
</script>

{#snippet headerActions()}
    <span class="text-sm text-muted-foreground">{articles.length} gesamt</span>
    <Button onclick={() => router.visit(ArticleController.create.url())}>
        <Plus class="size-4" />
        Artikel anlegen
    </Button>
{/snippet}

<AppHead title="Magazin — Admin" />

<div class="flex h-full flex-1 flex-col gap-4 p-4">
    <h1 class="sr-only">Magazin-Artikel</h1>

    <AdminDataTable
        data={articles}
        {columns}
        searchFields={(article) => [article.title, article.teaser]}
        searchPlaceholder="Titel oder Teaser filtern…"
        emptyText="Noch kein Artikel angelegt."
        noMatchText="Kein Artikel passt zur Suche."
    />
</div>
