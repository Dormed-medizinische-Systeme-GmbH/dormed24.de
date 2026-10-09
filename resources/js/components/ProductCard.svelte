<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { Percent } from '@lucide/svelte';
    import * as ProductController from '@/actions/App/Http/Controllers/ProductController';
    import { formatPrice } from '@/lib/currency';

    type ProductImage = { id: number; url: string; sort_order: number };

    type ProductCardProduct = {
        id: number;
        name: string;
        price: string;
        old_price?: string | null;
        manufacturer: { id: number; name: string } | null;
        images: ProductImage[];
    };

    let { product }: { product: ProductCardProduct } = $props();
</script>

<Link
    href={ProductController.show.url(product.id)}
    class="group relative flex h-full flex-col overflow-hidden rounded-[1.4rem] border border-border bg-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-lg"
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
    <div class="flex flex-col gap-1 px-4 pb-5 pt-4">
        <p
            class="line-clamp-2 min-h-12 text-[15px] font-medium leading-6 text-foreground transition-colors group-hover:text-brand-accent"
        >
            {product.name}
        </p>
        {#if product.manufacturer}
            <p class="text-sm text-muted-foreground">
                {product.manufacturer.name}
            </p>
        {/if}
        <div class="flex items-baseline gap-2 pt-1">
            {#if product.old_price}
                <span class="text-sm text-muted-foreground line-through">
                    {formatPrice(product.old_price)}
                </span>
            {/if}
            <p class="text-base font-semibold text-primary">
                {formatPrice(product.price)}*
            </p>
        </div>
    </div>
</Link>
