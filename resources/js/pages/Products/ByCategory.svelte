<script lang="ts">
    import { InfiniteScroll, Link, router } from '@inertiajs/svelte';
    import AppFooter from '@/components/AppFooter.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ProductCard from '@/components/ProductCard.svelte';
    import ShopHeader from '@/components/ShopHeader.svelte';
    import * as CategoryController from '@/actions/App/Http/Controllers/CategoryController';
    import * as ProductController from '@/actions/App/Http/Controllers/ProductController';

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
        id: number;
        name: string;
        slug: string;
        description: string | null;
    };

    let {
        category,
        products,
        total,
        sort = 'name_asc',
    }: {
        category: Category;
        products: { data: Product[] };
        total: number;
        sort: string;
    } = $props();

    const sortOptions = [
        { value: 'name_asc', label: 'Name A-Z' },
        { value: 'name_desc', label: 'Name Z-A' },
        { value: 'price_asc', label: 'Preis aufsteigend' },
        { value: 'price_desc', label: 'Preis absteigend' },
    ];

    function onSortChange(value: string) {
        const url = new URL(window.location.href);
        url.searchParams.set('sort', value);
        url.searchParams.delete('page');
        router.visit(url.toString(), { preserveScroll: false });
    }
</script>

<AppHead
    canonical={CategoryController.show.url(category.slug)}
    title={category.name}
    description={category.description ??
        `${category.name} – Medizintechnik im dormed24-Sortiment direkt online bestellen.`}
/>

<div class="flex min-h-screen flex-col bg-muted">
    <ShopHeader />

    <main class="mx-auto flex-1 max-w-7xl px-4 py-8 lg:px-8">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-foreground">
                    {category.name}
                </h1>
                {#if category.description}
                    <p class="mt-1 text-sm text-muted-foreground">
                        {category.description}
                    </p>
                {:else}
                    <p class="mt-1 text-sm text-muted-foreground">
                        {total} Produkte
                    </p>
                {/if}
            </div>
            <select
                value={sort}
                onchange={(e) => onSortChange(e.currentTarget.value)}
                class="rounded border border-border bg-white px-3 py-2 text-sm text-muted-foreground shadow-sm focus:border-primary focus:outline-none"
            >
                {#each sortOptions as option (option.value)}
                    <option value={option.value}>{option.label}</option>
                {/each}
            </select>
        </div>

        {#if products.data.length === 0}
            <div class="py-16 text-center">
                <p class="text-muted-foreground">
                    Keine Produkte in dieser Kategorie.
                </p>
                <Link
                    href={ProductController.index.url()}
                    class="mt-4 inline-block text-sm text-brand-accent hover:underline"
                >
                    Alle Produkte anzeigen
                </Link>
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
