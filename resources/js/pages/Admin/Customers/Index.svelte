<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Kunden', href: '/admin/customers' }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ChevronDown from '@lucide/svelte/icons/chevron-down';
    import { getCoreRowModel } from '@tanstack/table-core';
    import type {
        ColumnDef,
        RowSelectionState,
        VisibilityState,
    } from '@tanstack/table-core';
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

    type Customer = {
        id: number;
        name: string;
        email: string;
        email_verified_at: string | null;
        created_at: string;
    };

    type Paginator = {
        data: Customer[];
        total: number;
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        prev_page_url: string | null;
        next_page_url: string | null;
    };

    type Filters = {
        search: string;
    };

    let { customers, filters }: { customers: Paginator; filters: Filters } =
        $props();

    let search = $state(filters.search);

    $effect(() => {
        search = filters.search;
    });

    function submitSearch(value = search) {
        const term = value.trim();

        router.get('/admin/customers', term === '' ? {} : { search: term }, {
            preserveScroll: true,
            replace: true,
        });
    }

    function visitPage(url: string | null) {
        if (!url) {
            return;
        }

        router.visit(url, {
            preserveScroll: true,
            preserveState: true,
        });
    }

    const columns: ColumnDef<Customer>[] = [
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
            header: 'Name',
            cell: ({ row }) =>
                renderComponent(TableCellLink, {
                    href: `/admin/customers/${row.original.id}`,
                    label: row.original.name,
                }),
        },
        { accessorKey: 'email', header: 'E-Mail' },
        {
            accessorKey: 'email_verified_at',
            header: 'Verifiziert',
            cell: ({ row }) =>
                row.original.email_verified_at
                    ? new Date(
                          row.original.email_verified_at,
                      ).toLocaleDateString('de-DE')
                    : '—',
        },
        {
            accessorKey: 'created_at',
            header: 'Registriert',
            cell: ({ row }) =>
                new Date(row.original.created_at).toLocaleDateString('de-DE'),
        },
    ];

    let rowSelection = $state<RowSelectionState>({});
    let columnVisibility = $state<VisibilityState>({});

    const table = createSvelteTable({
        get data() {
            return customers.data;
        },
        columns,
        getCoreRowModel: getCoreRowModel(),
        onRowSelectionChange: (u) => {
            rowSelection = typeof u === 'function' ? u(rowSelection) : u;
        },
        onColumnVisibilityChange: (u) => {
            columnVisibility =
                typeof u === 'function' ? u(columnVisibility) : u;
        },
        state: {
            get rowSelection() {
                return rowSelection;
            },
            get columnVisibility() {
                return columnVisibility;
            },
        },
    });
</script>

<AppHead title="Kunden — Admin" />

<div class="flex h-full flex-1 flex-col gap-4 p-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Kunden</h1>
        <span class="text-sm text-muted-foreground"
            >{customers.total} gesamt</span
        >
    </div>

    <form
        class="flex items-center gap-2"
        onsubmit={(event) => {
            event.preventDefault();
            submitSearch();
        }}
    >
        <Input
            placeholder="Name oder E-Mail filtern…"
            bind:value={search}
            class="max-w-sm"
        />
        <Button type="submit" variant="outline">Suchen</Button>
        {#if filters.search !== ''}
            <Button
                type="button"
                variant="ghost"
                onclick={() => {
                    search = '';
                    submitSearch('');
                }}>Zurücksetzen</Button
            >
        {/if}

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
    </form>

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
                            Keine Kunden vorhanden.
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
                {#if customers.from !== null && customers.to !== null}
                    {customers.from}–{customers.to} von {customers.total}
                {:else}
                    0 von {customers.total}
                {/if}
            </span>
            <Button
                variant="outline"
                size="sm"
                onclick={() => visitPage(customers.prev_page_url)}
                disabled={!customers.prev_page_url}>Zurück</Button
            >
            <Button
                variant="outline"
                size="sm"
                onclick={() => visitPage(customers.next_page_url)}
                disabled={!customers.next_page_url}>Weiter</Button
            >
        </div>
    </div>
</div>
