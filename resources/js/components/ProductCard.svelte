<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import * as ProductController from '@/actions/App/Http/Controllers/ProductController';
    import { formatPrice } from '@/lib/currency';

    type ProductImage = { id: number; url: string; sort_order: number };

    type ProductCardProduct = {
        id: number;
        name: string;
        price: string;
        manufacturer: { id: number; name: string } | null;
        images: ProductImage[];
    };

    let { product }: { product: ProductCardProduct } = $props();
</script>

<Link
    href={ProductController.show.url(product.id)}
    class="group flex h-full flex-col overflow-hidden rounded-[1.4rem] border border-border bg-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-lg"
>
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
        <p class="pt-1 text-base font-semibold text-primary">
            {formatPrice(product.price)}*
        </p>
    </div>
</Link>
