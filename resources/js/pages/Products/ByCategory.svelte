<script lang="ts">
    import { InfiniteScroll, Link } from '@inertiajs/svelte';
    import * as CategoryController from '@/actions/App/Http/Controllers/CategoryController';
    import * as ProductController from '@/actions/App/Http/Controllers/ProductController';
    import AppFooter from '@/components/AppFooter.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ProductCard from '@/components/ProductCard.svelte';
    import ProductFilterChips from '@/components/ProductFilterChips.svelte';
    import ProductFilterSheet from '@/components/ProductFilterSheet.svelte';
    import ShopBreadcrumbs from '@/components/ShopBreadcrumbs.svelte';
    import ShopHeader from '@/components/ShopHeader.svelte';
    import { visitWithManufacturerFilter } from '@/lib/productFilters';
    import type {
        ProductListFilterOptions,
        ProductListFilters,
    } from '@/lib/productFilters';

    type ProductImage = { id: number; url: string; sort_order: number };

    type Product = {
        id: number;
        name: string;
        price: string;
        description: string | null;
        manufacturer: { id: number; name: string } | null;
        images: ProductImage[];
    };

    type Category = {
        name: string;
        slug: string;
        description: string | null;
    };

    let {
        category,
        products,
        filters,
        filterOptions,
    }: {
        category: Category;
        products: { data: Product[] };
        filters: ProductListFilters;
        filterOptions: ProductListFilterOptions;
    } = $props();

    const hasActiveFilters = $derived(filters.manufacturers.length > 0);
</script>

<AppHead
    canonical={CategoryController.show.url(category.slug)}
    title={category.name}
    description={category.description ??
        `${category.name} – Medizintechnik im dormed24-Sortiment direkt online bestellen.`}
/>

<div class="flex min-h-screen flex-col bg-muted">
    <ShopHeader />

    <main class="mx-auto w-full flex-1 max-w-7xl px-4 py-6 lg:px-8">
        <h1 class="sr-only">{category.name}</h1>

        <div
            class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <ShopBreadcrumbs
                items={[
                    {
                        title: 'Alle Produkte',
                        href: ProductController.index.url(),
                    },
                    { title: category.name },
                ]}
            />
            {#if filterOptions.total > 0}
                <ProductFilterSheet {filters} {filterOptions} />
            {/if}
        </div>

        <ProductFilterChips {filters} {filterOptions} />

        {#if products.data.length === 0}
            <div class="py-16 text-center">
                {#if hasActiveFilters}
                    <p class="text-muted-foreground">
                        Keine Produkte passen zu dieser Filterauswahl.
                    </p>
                    <button
                        type="button"
                        onclick={() => visitWithManufacturerFilter([])}
                        class="mt-4 inline-block text-sm text-brand-accent hover:underline"
                    >
                        Filter zurücksetzen
                    </button>
                {:else}
                    <p class="text-muted-foreground">
                        Keine Produkte in dieser Kategorie.
                    </p>
                    <Link
                        href={ProductController.index.url()}
                        class="mt-4 inline-block text-sm text-brand-accent hover:underline"
                    >
                        Alle Produkte anzeigen
                    </Link>
                {/if}
            </div>
        {:else}
            <InfiniteScroll data="products">
                <div
                    class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4"
                >
                    {#each products.data as product (product.id)}
                        <ProductCard {product} />
                    {/each}
                </div>

                {#snippet loading()}
                    <div class="mt-8 flex justify-center">
                        <div
                            class="size-6 animate-spin rounded-full border-2 border-brand-accent border-t-transparent"
                        ></div>
                    </div>
                {/snippet}
            </InfiniteScroll>
        {/if}
    </main>

    <AppFooter />
</div>
