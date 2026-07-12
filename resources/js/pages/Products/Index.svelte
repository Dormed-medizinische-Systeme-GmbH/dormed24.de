<script lang="ts">
    import { InfiniteScroll, Link, router } from '@inertiajs/svelte';
    import * as ProductController from '@/actions/App/Http/Controllers/ProductController';
    import AppFooter from '@/components/AppFooter.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ProductCard from '@/components/ProductCard.svelte';
    import ShopHeader from '@/components/ShopHeader.svelte';

    type ProductImage = { id: number; url: string; sort_order: number };

    type Product = {
        id: number;
        name: string;
        price: string;
        description: string | null;
        manufacturer: { id: number; name: string } | null;
        images: ProductImage[];
    };

    let {
        products,
        total,
        query,
        sort = 'name_asc',
    }: {
        products: { data: Product[] };
        total: number;
        query: string;
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
    canonical={ProductController.index.url()}
    title={query ? `Suchergebnisse für „${query}"` : 'Produkte'}
    description={query
        ? `Suchergebnisse für „${query}" im dormed24-Sortiment – Medizintechnik für Praxis und Klinik.`
        : 'Unser gesamtes Sortiment an Medizintechnik, Diagnostik, Monitoring und Verbrauchsmaterial – direkt online bestellen.'}
/>

<div class="flex min-h-screen flex-col bg-muted">
    <ShopHeader />

    <main class="mx-auto flex-1 max-w-7xl px-4 py-8 lg:px-8">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                {#if query}
                    <h1 class="text-xl font-semibold text-foreground">
                        Suchergebnisse für „{query}"
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {total}
                        {total === 1 ? 'Ergebnis' : 'Ergebnisse'}
                    </p>
                {:else}
                    <h1 class="text-xl font-semibold text-foreground">
                        Alle Produkte
                    </h1>
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
                    Keine Produkte gefunden{query ? ` für „${query}"` : ''}.
                </p>
                {#if query}
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
                <div class="grid grid-cols-2 gap-5 lg:grid-cols-4">
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
