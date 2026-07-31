<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Hersteller', href: '/admin/manufacturers' }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ChevronDown from '@lucide/svelte/icons/chevron-down';
    import Plus from '@lucide/svelte/icons/plus';
    import {
        getCoreRowModel,
        getFilteredRowModel,
        getPaginationRowModel,
        getSortedRowModel,
    } from '@tanstack/table-core';
    import type {
        ColumnDef,
        ColumnFiltersState,
        PaginationState,
        RowSelectionState,
        SortingState,
        VisibilityState,
    } from '@tanstack/table-core';
    import * as AdminManufacturerController from '@/actions/App/Http/Controllers/Admin/ManufacturerController';
    import AppHead from '@/components/AppHead.svelte';
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
    import ManufacturerActions from './ManufacturerActions.svelte';

    type Manufacturer = {
        id: number;
        name: string;
        country: string | null;
        contact_email: string | null;
    };

    type Paginator = {
        data: Manufacturer[];
        total: number;
        current_page: number;
        last_page: number;
    };

    let { manufacturers }: { manufacturers: Paginator } = $props();

    const columns: ColumnDef<Manufacturer>[] = [
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
                renderComponent(ManufacturerActions, {
                    manufacturer: row.original,
                }),
            enableSorting: false,
            enableHiding: false,
        },
    ];

    let sorting = $state<SortingState>([]);
    let columnFilters = $state<ColumnFiltersState>([]);
    let rowSelection = $state<RowSelectionState>({});
    let pagination = $state<PaginationState>({ pageIndex: 0, pageSize: 15 });
    let columnVisibility = $state<VisibilityState>({});

    const table = createSvelteTable({
        get data() {
            return manufacturers.data;
        },
        columns,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        onSortingChange: (u) => {
            sorting = typeof u === 'function' ? u(sorting) : u;
        },
        onColumnFiltersChange: (u) => {
            columnFilters = typeof u === 'function' ? u(columnFilters) : u;
        },
        onRowSelectionChange: (u) => {
            rowSelection = typeof u === 'function' ? u(rowSelection) : u;
        },
        onPaginationChange: (u) => {
            pagination = typeof u === 'function' ? u(pagination) : u;
        },
        onColumnVisibilityChange: (u) => {
            columnVisibility =
                typeof u === 'function' ? u(columnVisibility) : u;
        },
        state: {
            get sorting() {
                return sorting;
            },
            get columnFilters() {
                return columnFilters;
            },
            get rowSelection() {
                return rowSelection;
            },
            get pagination() {
                return pagination;
            },
            get columnVisibility() {
                return columnVisibility;
            },
        },
    });
</script>

<AppHead title="Hersteller — Admin" />

<div class="flex h-full flex-1 flex-col gap-4 p-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Hersteller</h1>
        <div class="flex items-center gap-3">
            <span class="text-sm text-muted-foreground"
                >{manufacturers.total} gesamt</span
            >
            <Button
                onclick={() =>
                    router.visit(AdminManufacturerController.create.url())}
            >
                <Plus class="size-4" />
                Hersteller hinzufügen
            </Button>
        </div>
    </div>

    <div class="flex items-center gap-2">
        <Input
            placeholder="Name filtern…"
            value={(table.getColumn('name')?.getFilterValue() as string) ?? ''}
            oninput={(e) =>
                table.getColumn('name')?.setFilterValue(e.currentTarget.value)}
            class="max-w-sm"
        />
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                {#snippet children(props)}
                    <Button {...props} variant="outline" class="ms-auto">
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
                            Keine Hersteller vorhanden.
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
        <div class="flex items-center gap-2">
            <span class="text-sm text-muted-foreground">
                Seite {table.getState().pagination.pageIndex + 1} von {table.getPageCount()}
            </span>
            <Button
                variant="outline"
                size="sm"
                onclick={() => table.previousPage()}
                disabled={!table.getCanPreviousPage()}>Zurück</Button
            >
            <Button
                variant="outline"
                size="sm"
                onclick={() => table.nextPage()}
                disabled={!table.getCanNextPage()}>Weiter</Button
            >
        </div>
    </div>
</div>
