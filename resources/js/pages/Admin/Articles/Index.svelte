<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Magazin', href: '/admin/articles' }],
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
    import * as ArticleController from '@/actions/App/Http/Controllers/Admin/ArticleController';
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
    import ArticleActions from './ArticleActions.svelte';
    import StatusBadge from './StatusBadge.svelte';

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
    }: { articles: Article[]; categories: Record<string, string> } = $props();

    const columns: ColumnDef<Article>[] = [
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
                renderComponent(StatusBadge, { status: row.original.status }),
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
                renderComponent(ArticleActions, {
                    article: row.original,
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
            return articles;
        },
        columns,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        // Suche über Titel und Teaser.
        globalFilterFn: (row, _columnId, value: string) => {
            const needle = value.toLocaleLowerCase('de');

            return [row.original.title, row.original.teaser ?? ''].some(
                (field) => field.toLocaleLowerCase('de').includes(needle),
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

    <div class="flex items-center gap-2">
        <Input
            placeholder="Titel oder Teaser filtern…"
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
                            {articles.length === 0
                                ? 'Noch kein Artikel angelegt.'
                                : 'Kein Artikel passt zur Suche.'}
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
