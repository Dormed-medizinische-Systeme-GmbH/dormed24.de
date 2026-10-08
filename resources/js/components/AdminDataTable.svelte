<script lang="ts" generics="TData">
    import { router } from '@inertiajs/svelte';
    import ArrowDown from '@lucide/svelte/icons/arrow-down';
    import ArrowUp from '@lucide/svelte/icons/arrow-up';
    import ArrowUpDown from '@lucide/svelte/icons/arrow-up-down';
    import ChevronDown from '@lucide/svelte/icons/chevron-down';
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
    import type { Snippet } from 'svelte';
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

    let {
        data,
        columns,
        searchFields,
        searchPlaceholder,
        emptyText,
        noMatchText = 'Kein Eintrag passt zur Suche.',
        toolbar,
        rowHref,
        rowLabel,
        serverSearch,
    }: {
        data: TData[];
        /** Fachliche Spalten; Auswahl-Spalte ergänzt die Tabelle selbst. */
        columns: ColumnDef<TData>[];
        /** Felder, in denen die Suche ohne Groß-/Kleinschreibung sucht. */
        searchFields?: (row: TData) => (string | null | undefined)[];
        searchPlaceholder: string;
        /** Text, wenn es noch gar keine Einträge gibt. */
        emptyText: string;
        /** Text, wenn die Suche nichts findet. */
        noMatchText?: string;
        /** Weitere Filter rechts neben der Suche. */
        toolbar?: Snippet;
        /** Macht die ganze Zeile zum Link, z. B. auf die Detailseite. */
        rowHref?: (row: TData) => string;
        /** Vorleser-Text der Zeile, wenn sie ein Link ist. */
        rowLabel?: (row: TData) => string;
        /**
         * Suche auf dem Server statt in den geladenen Zeilen: Die Eingabe
         * wird erst mit Enter bzw. „Suchen“ abgeschickt.
         */
        serverSearch?: { value: string; submit: (value: string) => void };
    } = $props();

    const selectColumn: ColumnDef<TData> = {
        id: 'select',
        header: ({ table }) =>
            renderComponent(Checkbox, {
                checked: table.getIsAllPageRowsSelected(),
                indeterminate:
                    table.getIsSomePageRowsSelected() &&
                    !table.getIsAllPageRowsSelected(),
                onCheckedChange: (v) => table.toggleAllPageRowsSelected(!!v),
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
    };

    const allColumns = $derived([selectColumn, ...columns]);

    let sorting = $state<SortingState>([]);
    let globalFilter = $state('');
    let rowSelection = $state<RowSelectionState>({});
    let columnVisibility = $state<VisibilityState>({});

    const table = createSvelteTable({
        get data() {
            return data;
        },
        get columns() {
            return allColumns;
        },
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        globalFilterFn: (row, _columnId, value: string) => {
            const needle = value.toLocaleLowerCase('de');

            return (searchFields?.(row.original) ?? []).some((field) =>
                (field ?? '').toLocaleLowerCase('de').includes(needle),
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

    const selectedCount = $derived(Object.keys(rowSelection).length);

    // Lokal editierbar, folgt aber dem Wert, den der Server zurückgibt.
    let serverSearchText = $derived(serverSearch?.value ?? '');

    /**
     * Zeilenklick öffnet rowHref, außer der Klick galt einem eigenen
     * Bedienelement in der Zeile (Checkbox, Link, Menü).
     */
    function openRow(event: MouseEvent | KeyboardEvent, row: TData): void {
        const target = event.target as HTMLElement | null;

        if (!rowHref || target?.closest('a, button, input, select, textarea')) {
            return;
        }

        router.visit(rowHref(row));
    }

    function columnLabel(header: unknown, id: string): string {
        return typeof header === 'string' && header !== '' ? header : id;
    }
</script>

<div class="flex items-center gap-2">
    {#if serverSearch}
        <form
            class="flex min-w-0 flex-1 items-center gap-2"
            onsubmit={(event) => {
                event.preventDefault();
                serverSearch.submit(serverSearchText.trim());
            }}
        >
            <Input
                placeholder={searchPlaceholder}
                aria-label="Suche"
                bind:value={serverSearchText}
                class="min-w-0 flex-1"
            />
            <Button type="submit" variant="outline">Suchen</Button>
            {#if serverSearch.value !== ''}
                <Button
                    type="button"
                    variant="ghost"
                    onclick={() => {
                        serverSearchText = '';
                        serverSearch.submit('');
                    }}>Zurücksetzen</Button
                >
            {/if}
        </form>
    {:else}
        <Input
            placeholder={searchPlaceholder}
            aria-label="Suche"
            value={globalFilter}
            oninput={(e) => table.setGlobalFilter(e.currentTarget.value)}
            class="min-w-0 flex-1"
        />
    {/if}
    {@render toolbar?.()}
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
                    {columnLabel(column.columnDef.header, column.id)}
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
                        <Table.Head
                            class="has-[[role=checkbox]]:ps-3"
                            aria-sort={header.column.getIsSorted() === 'asc'
                                ? 'ascending'
                                : header.column.getIsSorted() === 'desc'
                                  ? 'descending'
                                  : undefined}
                        >
                            {#if header.isPlaceholder}
                                <!-- leer -->
                            {:else if header.column.getCanSort()}
                                <button
                                    type="button"
                                    class="-ms-2 inline-flex items-center gap-1 rounded px-2 py-1 hover:bg-muted"
                                    onclick={header.column.getToggleSortingHandler()}
                                >
                                    <FlexRender
                                        content={header.column.columnDef.header}
                                        context={header.getContext()}
                                    />
                                    {#if header.column.getIsSorted() === 'asc'}
                                        <ArrowUp class="size-3.5" />
                                    {:else if header.column.getIsSorted() === 'desc'}
                                        <ArrowDown class="size-3.5" />
                                    {:else}
                                        <ArrowUpDown
                                            class="size-3.5 text-muted-foreground"
                                        />
                                    {/if}
                                </button>
                            {:else}
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
                <Table.Row
                    data-state={row.getIsSelected() && 'selected'}
                    class={rowHref ? 'cursor-pointer' : undefined}
                    tabindex={rowHref ? 0 : undefined}
                    role={rowHref ? 'link' : undefined}
                    aria-label={rowHref ? rowLabel?.(row.original) : undefined}
                    onclick={(event) => openRow(event, row.original)}
                    onkeydown={(event) => {
                        if (
                            rowHref &&
                            (event.key === 'Enter' || event.key === ' ')
                        ) {
                            event.preventDefault();
                            openRow(event, row.original);
                        }
                    }}
                >
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
                        colspan={table.getVisibleLeafColumns().length}
                        class="h-24 text-center text-muted-foreground"
                    >
                        {data.length === 0 ? emptyText : noMatchText}
                    </Table.Cell>
                </Table.Row>
            {/each}
        </Table.Body>
    </Table.Root>
</div>

<div class="flex items-center justify-between">
    <span class="text-sm text-muted-foreground">
        {#if selectedCount > 0}
            {selectedCount} ausgewählt
        {/if}
    </span>
</div>
