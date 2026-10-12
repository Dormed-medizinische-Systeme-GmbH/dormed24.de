<script lang="ts">
    import { InfiniteScroll } from '@inertiajs/svelte';
    import AppFooter from '@/components/AppFooter.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ProductCard from '@/components/ProductCard.svelte';
    import ShopBreadcrumbs from '@/components/ShopBreadcrumbs.svelte';
    import ShopHeader from '@/components/ShopHeader.svelte';

    type ProductImage = {
        id: number;
        url: string;
        sort_order: number;
        width?: number | null;
        height?: number | null;
    };

    type Product = {
        id: number;
        name: string;
        slug: string;
        category_slug: string;
        price: string;
        description: string | null;
        manufacturer: { id: number; name: string } | null;
        images: ProductImage[];
    };

    let {
        products,
        query,
        total,
    }: {
        products: { data: Product[] };
        query: string;
        total: number;
    } = $props();

    const heading = $derived(
        query ? `Suchergebnisse für „${query}"` : 'Produktsuche',
    );
</script>

<AppHead title={heading}>
    <!-- Suchergebnisse sind keine eigenen Einstiegsseiten: nicht indexieren. -->
    <meta name="robots" content="noindex, follow" />
</AppHead>

<div class="flex min-h-screen flex-col bg-muted">
    <ShopHeader />

    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 lg:px-8">
        <h1 class="sr-only">{heading}</h1>

        <div class="mb-8">
            <ShopBreadcrumbs
                items={[
                    {
                        title: query ? `Suche „${query}"` : 'Suche',
                        inSchema: false,
                    },
                ]}
            />
        </div>

        {#if products.data.length === 0}
            <div class="py-16 text-center">
                <p class="text-muted-foreground">
                    {#if query}
                        Keine Produkte gefunden für „{query}".
                    {:else}
                        Gib oben einen Suchbegriff ein.
                    {/if}
                </p>
            </div>
        {:else}
            <p class="mb-4 text-sm text-muted-foreground">
                {total}
                {total === 1 ? 'Ergebnis' : 'Ergebnisse'}
            </p>

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
