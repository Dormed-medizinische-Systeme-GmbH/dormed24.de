<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { Percent, Star } from '@lucide/svelte';
    import * as ProductController from '@/actions/App/Http/Controllers/ProductController';
    import { formatPrice } from '@/lib/currency';

    type ProductImage = { id: number; url: string; sort_order: number };

    type ProductCardProduct = {
        id: number;
        name: string;
        description: string | null;
        price: string;
        old_price?: string | null;
        is_available?: boolean;
        manufacturer: { id: number; name: string } | null;
        images: ProductImage[];
        ratings_avg_stars?: number | string | null;
        ratings_count?: number;
    };

    let { product }: { product: ProductCardProduct } = $props();

    const isAvailable = $derived(product.is_available ?? true);
    const averageStars = $derived(Number(product.ratings_avg_stars ?? 0));
    const filledStars = $derived(Math.round(averageStars));
    const averageLabel = $derived(averageStars.toFixed(1).replace('.', ','));
</script>

<Link
    href={ProductController.show.url(product.id)}
    class="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-border bg-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-lg"
>
    {#if product.old_price}
        <span
            class="absolute top-3 left-3 z-10 inline-flex items-center gap-1.5 rounded-full bg-[linear-gradient(180deg,#5bbdeb_0%,#35a8e0_50%,#2a8dc0_100%)] py-1.5 pr-2.5 pl-2 text-[11px] leading-none font-semibold tracking-[0.08em] text-white uppercase shadow-[inset_0_1px_0_rgb(255_255_255/0.35),0_4px_12px_rgb(42_141_192/0.4)]"
        >
            <Percent class="size-3" aria-hidden="true" />
            Angebot
        </span>
    {/if}
    <div class="aspect-square w-full overflow-hidden bg-white">
        {#if product.images[0]}
            <img
                src={product.images[0].url}
                alt={product.name}
                class="size-full object-contain object-center transition-transform duration-500 group-hover:scale-105"
            />
        {:else}
            <div class="size-full bg-white"></div>
        {/if}
    </div>
    <div class="flex flex-1 flex-col gap-2 p-4">
        {#if product.manufacturer}
            <p
                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
                {product.manufacturer.name}
            </p>
        {/if}
        <p
            class="line-clamp-2 min-h-12 text-[15px] leading-6 font-semibold text-foreground transition-colors group-hover:text-brand-accent"
        >
            {product.name}
        </p>
        {#if product.ratings_count}
            <div
                class="flex items-center gap-0.5"
                role="img"
                aria-label="Bewertung: {averageLabel} von 5 Sternen"
            >
                {#each [1, 2, 3, 4, 5] as star (star)}
                    <Star
                        class="size-4 {star <= filledStars
                            ? 'fill-amber-400 text-amber-400'
                            : 'text-muted-foreground/30'}"
                        aria-hidden="true"
                    />
                {/each}
                <span class="ml-1.5 text-xs text-muted-foreground">
                    {product.ratings_count} Bewertungen
                </span>
            </div>
        {/if}
        {#if product.description}
            <p class="line-clamp-2 text-[13px] leading-5 text-muted-foreground">
                {product.description}
            </p>
        {/if}
        <div class="mt-auto flex items-end justify-between gap-3 pt-3">
            <div class="flex items-baseline gap-2">
                {#if product.old_price}
                    <span class="text-sm text-muted-foreground line-through">
                        {formatPrice(product.old_price)}
                    </span>
                {/if}
                <p class="text-lg font-bold text-primary">
                    {formatPrice(product.price)}*
                </p>
            </div>
            {#if isAvailable}
                <span
                    class="rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground"
                >
                    Zum Produkt
                </span>
            {:else}
                <span
                    class="rounded-lg bg-muted px-3 py-2 text-sm font-semibold text-muted-foreground"
                >
                    Nicht verfügbar
                </span>
            {/if}
        </div>
    </div>
</Link>
