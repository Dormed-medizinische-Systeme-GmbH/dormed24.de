<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Produkte', href: '/admin/products' }],
    };
</script>

<script lang="ts">
    import { router, setLayoutProps } from '@inertiajs/svelte';
    import ExternalLink from '@lucide/svelte/icons/external-link';
    import Plus from '@lucide/svelte/icons/plus';
    import type { ColumnDef } from '@tanstack/table-core';
    import * as AdminProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
    import * as ProductController from '@/actions/App/Http/Controllers/ProductController';
    import AdminDataTable from '@/components/AdminDataTable.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import RowActions from '@/components/RowActions.svelte';
    import { Button } from '@/components/ui/button';
    import { renderComponent } from '@/components/ui/data-table';
    import * as Select from '@/components/ui/select';
    import { formatPrice } from '@/lib/currency';
    import AvailabilityBadge from './AvailabilityBadge.svelte';
    import ProductCell from './ProductCell.svelte';

    type Product = {
        slug: string;
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
            sortingFn: (a, b) =>
                Number(a.original.price) - Number(b.original.price),
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
                renderComponent(RowActions, {
                    editHref: AdminProductController.edit.url(row.original.id),
                    links: row.original.category_slug
                        ? [
                              {
                                  href: ProductController.show.url({
                                      category: row.original.category_slug,
                                      product: row.original.slug,
                                  }),
                                  label: 'Im Shop ansehen',
                                  icon: ExternalLink,
                                  external: true,
                              },
                          ]
                        : [],
                    destroyHref: AdminProductController.destroy.url(
                        row.original.id,
                    ),
                    confirmMessage: `Produkt „${row.original.name}“ wirklich löschen?`,
                }),
            enableSorting: false,
            enableHiding: false,
        },
    ];

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

{#snippet categoryFilter()}
    <Select.Root type="single" bind:value={selectedCategory}>
        <Select.Trigger class="w-56 shrink-0" aria-label="Kategorie">
            {selectedCategoryLabel}
        </Select.Trigger>
        <Select.Content align="end">
            <Select.Item value={ALL_CATEGORIES}>Alle Kategorien</Select.Item>
            {#each categories as category (category.slug)}
                <Select.Item value={category.slug}>
                    {category.name}
                </Select.Item>
            {/each}
        </Select.Content>
    </Select.Root>
{/snippet}

<AppHead title="Produkte — Admin" />

<div class="flex h-full flex-1 flex-col gap-4 p-4">
    <h1 class="sr-only">Produkte</h1>

    <AdminDataTable
        data={visibleProducts}
        {columns}
        searchFields={(product) => [product.name, product.manufacturer?.name]}
        searchPlaceholder="Produkt oder Hersteller filtern…"
        emptyText="Keine Produkte vorhanden."
        noMatchText="Kein Produkt passt zu Suche oder Kategorie."
        toolbar={categoryFilter}
    />
</div>
