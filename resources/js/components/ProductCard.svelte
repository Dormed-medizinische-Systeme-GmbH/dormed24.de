<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { Percent, ShoppingBag } from '@lucide/svelte';
    import * as ProductController from '@/actions/App/Http/Controllers/ProductController';
    import { formatPrice } from '@/lib/currency';

    type ProductImage = { id: number; url: string; sort_order: number };

    type ProductCardProduct = {
        id: number;
        name: string;
        slug: string;
        category_slug: string;
        description?: string | null;
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
    href={ProductController.show.url({
        category: product.category_slug,
        product: product.slug,
    })}
    class="group relative flex h-full cursor-pointer flex-col overflow-hidden rounded-[15px] bg-white leading-[normal] shadow-[0_5px_20px_rgba(0,0,0,0.1)] transition duration-300 ease-[ease] hover:-translate-y-[5px] hover:shadow-[0_10px_25px_rgba(0,0,0,0.15)]"
>
    {#if product.old_price}
        <span
            class="absolute top-3 left-3 z-10 inline-flex items-center gap-[5px] rounded-full bg-[linear-gradient(180deg,#5bbdeb_0%,#35a8e0_50%,#2a8dc0_100%)] py-1.5 pr-[11px] pl-[9px] text-[11px] leading-none font-semibold tracking-[0.08em] text-white uppercase shadow-[inset_0_1px_0_rgba(255,255,255,0.35),0_4px_12px_rgba(42,141,192,0.4)]"
        >
            <Percent class="size-3" strokeWidth={2.5} aria-hidden="true" />
            Angebot
        </span>
    {/if}
    <div class="aspect-square w-full overflow-hidden">
        {#if product.images[0]}
            <img
                src={product.images[0].url}
                alt={product.name}
                class="block size-full object-contain transition-transform duration-500 ease-[ease] group-hover:scale-105"
            />
        {/if}
    </div>
    <div class="flex flex-1 flex-col p-5">
        {#if product.manufacturer}
            <div
                class="mb-[5px] text-[11px] font-semibold tracking-[1px] text-[#71717A] uppercase"
            >
                {product.manufacturer.name}
            </div>
        {/if}
        <h2
            class="mb-3 line-clamp-2 text-[18px] font-bold tracking-[-0.5px] text-[#18181B] max-[400px]:text-base"
        >
            {product.name}
        </h2>
        {#if product.ratings_count}
            <div
                class="mb-3 flex items-center gap-0.5"
                role="img"
                aria-label="Bewertung: {averageLabel} von 5 Sternen"
            >
                {#each [1, 2, 3, 4, 5] as star (star)}
                    <svg
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill={star <= filledStars ? '#FFD700' : 'none'}
                        stroke={star <= filledStars ? '#FFD700' : '#D4D4D8'}
                        stroke-width={star <= filledStars ? 0.5 : 1}
                        aria-hidden="true"
                    >
                        <polygon
                            points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"
                        />
                    </svg>
                {/each}
                <span class="ml-1.5 text-[11px] text-[#71717A]">
                    {product.ratings_count} Bewertungen
                </span>
            </div>
        {/if}
        <div class="mb-3 flex items-baseline gap-2 max-[400px]:mb-[5px]">
            {#if product.old_price}
                <span class="mb-0.5 text-[13px] text-[#A1A1AA] line-through">
                    {formatPrice(product.old_price)}
                </span>
            {/if}
            <span class="text-[20px] font-bold text-[#18181B]">
                {formatPrice(product.price)}*
            </span>
        </div>
        {#if product.description}
            <p
                class="mt-px mb-3 line-clamp-2 text-[13px] leading-[1.4] text-[#52525B]"
            >
                {product.description}
            </p>
        {/if}
        <div
            class="mt-auto flex justify-end max-[400px]:flex-col max-[400px]:items-start max-[400px]:gap-2.5 {product.description
                ? ''
                : 'pt-2'}"
        >
            {#if isAvailable}
                <span
                    class="group/btn relative inline-flex items-center gap-1.5 overflow-hidden rounded-[10px] bg-[linear-gradient(45deg,#18181B,#27272A)] px-[15px] py-2 text-[13px] font-semibold text-white shadow-[0_3px_10px_rgba(0,0,0,0.1)] transition duration-300 before:absolute before:inset-y-0 before:left-[-100%] before:w-full before:bg-[linear-gradient(90deg,transparent,rgba(255,255,255,0.1),transparent)] before:transition-[left] before:duration-500 before:content-[''] hover:-translate-y-0.5 hover:bg-[linear-gradient(45deg,#27272A,#3F3F46)] hover:shadow-[0_5px_15px_rgba(0,0,0,0.15)] hover:before:left-full max-[400px]:w-full max-[400px]:justify-center"
                >
                    Zum Produkt
                    <ShoppingBag
                        class="size-5 transition-transform duration-300 group-hover/btn:scale-110 group-hover/btn:rotate-[-10deg]"
                        aria-hidden="true"
                    />
                </span>
            {:else}
                <span
                    aria-disabled="true"
                    class="relative inline-flex cursor-not-allowed items-center gap-1.5 overflow-hidden rounded-[10px] bg-[#DDE1E8] px-[15px] py-2 text-[13px] font-semibold text-[#4F5663] max-[400px]:w-full max-[400px]:justify-center"
                >
                    Nicht verfügbar
                    <ShoppingBag class="size-5" aria-hidden="true" />
                </span>
            {/if}
        </div>
    </div>
</Link>
