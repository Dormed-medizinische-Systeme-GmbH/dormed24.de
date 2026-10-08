<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Social-Media', href: '/admin/social-posts' }],
    };
</script>

<script lang="ts">
    import { router, setLayoutProps } from '@inertiajs/svelte';
    import Plus from '@lucide/svelte/icons/plus';
    import type { ColumnDef } from '@tanstack/table-core';
    import * as SocialPostController from '@/actions/App/Http/Controllers/Admin/SocialPostController';
    import AdminDataTable from '@/components/AdminDataTable.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import RowActions from '@/components/RowActions.svelte';
    import StatusBadge from '@/components/StatusBadge.svelte';
    import TableCellLink from '@/components/TableCellLink.svelte';
    import { Button } from '@/components/ui/button';
    import { renderComponent } from '@/components/ui/data-table';
    import { formatDateTime } from '@/lib/date';
    import type { StatusMap } from '@/lib/status';
    import PlatformBadges from './PlatformBadges.svelte';

    type Post = {
        id: number;
        title: string;
        content: string;
        platforms: string[];
        status: string;
        scheduled_at: string | null;
        updated_at: string;
    };

    let {
        posts,
        platforms,
        statuses,
    }: {
        posts: Post[];
        platforms: Record<string, string>;
        statuses: StatusMap;
    } = $props();

    const columns: ColumnDef<Post>[] = [
        {
            accessorKey: 'title',
            header: 'Titel',
            cell: ({ row }) =>
                renderComponent(TableCellLink, {
                    href: SocialPostController.edit.url(row.original.id),
                    label: row.original.title,
                }),
        },
        {
            accessorKey: 'content',
            header: 'Text',
            cell: ({ row }) =>
                row.original.content.length > 50
                    ? `${row.original.content.slice(0, 50)}…`
                    : row.original.content,
            enableSorting: false,
        },
        {
            id: 'platforms',
            header: 'Plattformen',
            cell: ({ row }) =>
                renderComponent(PlatformBadges, {
                    platforms: row.original.platforms,
                    labels: platforms,
                }),
            enableSorting: false,
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
            accessorKey: 'scheduled_at',
            header: 'Geplant für',
            cell: ({ row }) =>
                row.original.scheduled_at
                    ? formatDateTime(row.original.scheduled_at)
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
                    editHref: SocialPostController.edit.url(row.original.id),
                    destroyHref: SocialPostController.destroy.url(
                        row.original.id,
                    ),
                    confirmMessage: `Beitrag „${row.original.title}“ wirklich löschen?`,
                }),
            enableSorting: false,
            enableHiding: false,
        },
    ];

    // Anzahl + „Beitrag anlegen“ rechts im Admin-Header.
    $effect(() => {
        setLayoutProps({ actions: headerActions });
    });
</script>

{#snippet headerActions()}
    <span class="text-sm text-muted-foreground">{posts.length} gesamt</span>
    <Button onclick={() => router.visit(SocialPostController.create.url())}>
        <Plus class="size-4" />
        Beitrag anlegen
    </Button>
{/snippet}

<AppHead title="Social-Media — Admin" />

<div class="flex h-full flex-1 flex-col gap-4 p-4">
    <h1 class="sr-only">Social-Media-Beiträge</h1>

    <AdminDataTable
        data={posts}
        {columns}
        searchFields={(post) => [post.title, post.content]}
        searchPlaceholder="Titel oder Text filtern…"
        emptyText="Noch kein Beitrag angelegt."
        noMatchText="Kein Beitrag passt zur Suche."
    />
</div>
