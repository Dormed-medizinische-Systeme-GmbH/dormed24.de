<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Produkte', href: '/admin/products' }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ChevronDown from '@lucide/svelte/icons/chevron-down';
    import Plus from '@lucide/svelte/icons/plus';
    import {
        getCoreRowModel,
        getFilteredRowModel,
        getSortedRowModel,
    } from '@tanstack/table-core';
    import type {
        ColumnDef,
        ColumnFiltersState,
        RowSelectionState,
        SortingState,
        VisibilityState,
    } from '@tanstack/table-core';
    import * as AdminProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
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
    import { formatPrice } from '@/lib/currency';
    import AvailabilityBadge from './AvailabilityBadge.svelte';
    import ProductActions from './ProductActions.svelte';
    import ProductCell from './ProductCell.svelte';

    type Product = {
        id: number;
        name: string;
        price: string;
        is_available: boolean;
        manufacturer: { id: number; name: string } | null;
    };

    let { products }: { products: Product[] } = $props();

    const columns: ColumnDef<Product>[] = [
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
            accessorKey: 'name',
            header: 'Produkt',
            cell: ({ row }) =>
                renderComponent(ProductCell, {
                    name: row.original.name,
                    manufacturer: row.original.manufacturer,
                }),
        },
        {
            accessorKey: 'price',
            header: 'Preis',
            cell: ({ row }) => formatPrice(row.original.price),
        },
        {
            accessorKey: 'is_available',
            header: 'Verfügbarkeit',
            cell: ({ row }) =>
                renderComponent(AvailabilityBadge, {
                    isAvailable: row.original.is_available,
                }),
        },
        {
            id: 'actions',
            header: '',
            cell: ({ row }) =>
                renderComponent(ProductActions, { product: row.original }),
            enableSorting: false,
            enableHiding: false,
        },
    ];

    let sorting = $state<SortingState>([]);
    let columnFilters = $state<ColumnFiltersState>([]);
    let rowSelection = $state<RowSelectionState>({});
    let columnVisibility = $state<VisibilityState>({});

    const table = createSvelteTable({
        get data() {
            return products;
        },
        columns,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        onSortingChange: (u) => {
            sorting = typeof u === 'function' ? u(sorting) : u;
        },
        onColumnFiltersChange: (u) => {
            columnFilters = typeof u === 'function' ? u(columnFilters) : u;
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
            get columnFilters() {
                return columnFilters;
            },
            get rowSelection() {
                return rowSelection;
            },
            get columnVisibility() {
                return columnVisibility;
            },
        },
    });
</script>

<AppHead title="Produkte — Admin" />

<div class="flex h-full flex-1 flex-col gap-4 p-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Produkte</h1>
        <div class="flex items-center gap-3">
            <span class="text-sm text-muted-foreground"
                >{products.length} gesamt</span
            >
            <Button
                onclick={() =>
                    router.visit(AdminProductController.create.url())}
            >
                <Plus class="size-4" />
                Produkt hinzufügen
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
                            Keine Produkte vorhanden.
                        </Table.Cell>
                    </Table.Row>
                {/each}
            </Table.Body>
        </Table.Root>
    </div>

    <div class="flex items-center justify-between gap-2">
        <span class="text-sm text-muted-foreground">
            {#if Object.keys(rowSelection).length > 0}
                {Object.keys(rowSelection).length} ausgewählt
            {/if}
        </span>
    </div>
</div>
