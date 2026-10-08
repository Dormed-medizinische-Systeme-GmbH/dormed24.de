<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Social-Media', href: '/admin/social-posts' }],
    };
</script>

<script lang="ts">
    import { router, setLayoutProps } from '@inertiajs/svelte';
    import ChevronDown from '@lucide/svelte/icons/chevron-down';
    import Plus from '@lucide/svelte/icons/plus';
    import {
        getCoreRowModel,
        getFilteredRowModel,
        getSortedRowModel,
    } from '@tanstack/table-core';
    import type {
        ColumnDef,
        RowSelectionState,
        SortingState,
        VisibilityState,
    } from '@tanstack/table-core';
    import * as SocialPostController from '@/actions/App/Http/Controllers/Admin/SocialPostController';
    import AppHead from '@/components/AppHead.svelte';
    import TableCellLink from '@/components/TableCellLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import {
        FlexRender,
        createSvelteTable,
        renderComponent,
    } from '@/components/ui/data-table';
    import {
        DropdownMenu,
        DropdownMenuCheckboxItem,
        DropdownMenuContent,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import { Input } from '@/components/ui/input';
    import * as Table from '@/components/ui/table';
    import { formatDateTime } from '@/lib/date';
    import PlatformBadges from './PlatformBadges.svelte';
    import SocialPostActions from './SocialPostActions.svelte';
    import StatusBadge from './StatusBadge.svelte';

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
    }: { posts: Post[]; platforms: Record<string, string> } = $props();

    const columns: ColumnDef<Post>[] = [
        {
            id: 'select',
            header: ({ table }) =>
                renderComponent(Checkbox, {
                    checked: table.getIsAllPageRowsSelected(),
                    indeterminate:
                        table.getIsSomePageRowsSelected() &&
                        !table.getIsAllPageRowsSelected(),
                    onCheckedChange: (v) =>
                        table.toggleAllPageRowsSelected(!!v),
                    'aria-label': 'Alle auswählen',
                }),
            cell: ({ row }) =>
                renderComponent(Checkbox, {
                    checked: row.getIsSelected(),
                    onCheckedChange: (v) => row.toggleSelected(!!v),
                    'aria-label': 'Zeile auswählen',
                }),
            enableSorting: false,
            enableHiding: false,
        },
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
                renderComponent(StatusBadge, { status: row.original.status }),
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
                renderComponent(SocialPostActions, {
                    post: row.original,
                }),
            enableSorting: false,
            enableHiding: false,
        },
    ];

    let sorting = $state<SortingState>([]);
    let globalFilter = $state('');
    let rowSelection = $state<RowSelectionState>({});
    let columnVisibility = $state<VisibilityState>({});

    const table = createSvelteTable({
        get data() {
            return posts;
        },
        columns,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        // Suche über Titel und Text.
        globalFilterFn: (row, _columnId, value: string) => {
            const needle = value.toLocaleLowerCase('de');

            return [row.original.title, row.original.content].some((field) =>
                field.toLocaleLowerCase('de').includes(needle),
            );
        },
        onSortingChange: (u) => {
            sorting = typeof u === 'function' ? u(sorting) : u;
        },
        onGlobalFilterChange: (u) => {
            globalFilter = typeof u === 'function' ? u(globalFilter) : u;
        },
        onRowSelectionChange: (u) => {
            rowSelection = typeof u === 'function' ? u(rowSelection) : u;
        },
        onColumnVisibilityChange: (u) => {
            columnVisibility =
                typeof u === 'function' ? u(columnVisibility) : u;
        },
        state: {
            get sorting() {
                return sorting;
            },
            get globalFilter() {
                return globalFilter;
            },
            get rowSelection() {
                return rowSelection;
            },
            get columnVisibility() {
                return columnVisibility;
            },
        },
    });

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

    <div class="flex items-center gap-2">
        <Input
            placeholder="Titel oder Text filtern…"
            value={globalFilter}
            oninput={(e) => table.setGlobalFilter(e.currentTarget.value)}
            class="min-w-0 flex-1"
        />
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                {#snippet children(props)}
                    <Button {...props} variant="outline" class="shrink-0">
                        Spalten <ChevronDown class="ml-2 size-4" />
                    </Button>
                {/snippet}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {#each table
                    .getAllColumns()
                    .filter((c) => c.getCanHide()) as column (column.id)}
                    <DropdownMenuCheckboxItem
                        checked={column.getIsVisible()}
                        onCheckedChange={(v) => column.toggleVisibility(v)}
                    >
                        {typeof column.columnDef.header === 'string'
                            ? column.columnDef.header
                            : column.id}
                    </DropdownMenuCheckboxItem>
                {/each}
            </DropdownMenuContent>
        </DropdownMenu>
    </div>

    <div class="rounded-md border">
        <Table.Root>
            <Table.Header>
                {#each table.getHeaderGroups() as headerGroup (headerGroup.id)}
                    <Table.Row>
                        {#each headerGroup.headers as header (header.id)}
                            <Table.Head class="has-[[role=checkbox]]:ps-3">
                                {#if !header.isPlaceholder}
                                    <FlexRender
                                        content={header.column.columnDef.header}
                                        context={header.getContext()}
                                    />
                                {/if}
                            </Table.Head>
                        {/each}
                    </Table.Row>
                {/each}
            </Table.Header>
            <Table.Body>
                {#each table.getRowModel().rows as row (row.id)}
                    <Table.Row data-state={row.getIsSelected() && 'selected'}>
                        {#each row.getVisibleCells() as cell (cell.id)}
                            <Table.Cell class="has-[[role=checkbox]]:ps-3">
                                <FlexRender
                                    content={cell.column.columnDef.cell}
                                    context={cell.getContext()}
                                />
                            </Table.Cell>
                        {/each}
                    </Table.Row>
                {:else}
                    <Table.Row>
                        <Table.Cell
                            colspan={columns.length}
                            class="h-24 text-center text-muted-foreground"
                        >
                            {posts.length === 0
                                ? 'Noch kein Beitrag angelegt.'
                                : 'Kein Beitrag passt zur Suche.'}
                        </Table.Cell>
                    </Table.Row>
                {/each}
            </Table.Body>
        </Table.Root>
    </div>

    <div class="flex items-center justify-between">
        <span class="text-sm text-muted-foreground">
            {#if Object.keys(rowSelection).length > 0}
                {Object.keys(rowSelection).length} ausgewählt
            {/if}
        </span>
    </div>
</div>
