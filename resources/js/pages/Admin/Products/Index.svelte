<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Produkte', href: '/admin/products' }],
    };
</script>

<script lang="ts">
    import { router, setLayoutProps } from '@inertiajs/svelte';
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
    import { Input } from '@/components/ui/input';
    import * as Select from '@/components/ui/select';
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
        category_slug: string | null;
        manufacturer: { id: number; name: string } | null;
    };

    type Category = { slug: string; name: string };

    let {
        products,
        categories,
    }: { products: Product[]; categories: Category[] } = $props();

    // Kategorie-Filter: ALL_CATEGORIES = keine Einschränkung.
    const ALL_CATEGORIES = 'alle';
    let selectedCategory = $state(ALL_CATEGORIES);

    const selectedCategoryLabel = $derived(
        categories.find((category) => category.slug === selectedCategory)
            ?.name ?? 'Alle Kategorien',
    );

    const visibleProducts = $derived(
        selectedCategory === ALL_CATEGORIES
            ? products
            : products.filter(
                  (product) => product.category_slug === selectedCategory,
              ),
    );

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

    const table = createSvelteTable({
        get data() {
            return visibleProducts;
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
        },
    });

    // Anzahl + „Produkt hinzufügen“ rechts im Admin-Header.
    $effect(() => {
        setLayoutProps({ actions: headerActions });
    });
</script>

{#snippet headerActions()}
    <span class="text-sm text-muted-foreground">{products.length} gesamt</span>
    <Button onclick={() => router.visit(AdminProductController.create.url())}>
        <Plus class="size-4" />
        Produkt hinzufügen
    </Button>
{/snippet}

<AppHead title="Produkte — Admin" />

<div class="flex h-full flex-1 flex-col gap-4 p-4">
    <h1 class="sr-only">Produkte</h1>

    <div class="flex items-center gap-2">
        <Input
            placeholder="Name filtern…"
            value={(table.getColumn('name')?.getFilterValue() as string) ?? ''}
            oninput={(e) =>
                table.getColumn('name')?.setFilterValue(e.currentTarget.value)}
            class="min-w-0 flex-1"
        />

        <Select.Root type="single" bind:value={selectedCategory}>
            <Select.Trigger class="w-56 shrink-0" aria-label="Kategorie">
                {selectedCategoryLabel}
            </Select.Trigger>
            <Select.Content align="end">
                <Select.Item value={ALL_CATEGORIES}>Alle Kategorien</Select.Item
                >
                {#each categories as category (category.slug)}
                    <Select.Item value={category.slug}>
                        {category.name}
                    </Select.Item>
                {/each}
            </Select.Content>
        </Select.Root>
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
