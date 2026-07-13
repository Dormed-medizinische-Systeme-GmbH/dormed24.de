<script lang="ts">
    import ArrowLeft from '@lucide/svelte/icons/arrow-left';
    import ArrowRight from '@lucide/svelte/icons/arrow-right';
    import ProductCard from '@/components/ProductCard.svelte';

    type ProductImage = { id: number; url: string; sort_order: number };

    type ShowcaseProduct = {
        id: number;
        name: string;
        price: string;
        manufacturer: { id: number; name: string } | null;
        images: ProductImage[];
    };

    let {
        title = 'Entdecken Sie unser Sortiment',
        randomProducts = [],
    }: {
        title?: string;
        randomProducts?: ShowcaseProduct[];
    } = $props();

    let activeProductIndex = $state(0);
    const activeProduct = $derived(
        randomProducts[activeProductIndex] ?? randomProducts[0],
    );

    $effect(() => {
        if (activeProductIndex >= randomProducts.length) {
            activeProductIndex = 0;
        }
    });

    function showPreviousProduct() {
        if (randomProducts.length === 0) {
            return;
        }

        activeProductIndex =
            activeProductIndex === 0
                ? randomProducts.length - 1
                : activeProductIndex - 1;
    }

    function showNextProduct() {
        if (randomProducts.length === 0) {
            return;
        }

        activeProductIndex =
            activeProductIndex === randomProducts.length - 1
                ? 0
                : activeProductIndex + 1;
    }
</script>

{#if randomProducts.length > 0 && activeProduct}
    <section class="bg-muted">
        <div class="mx-auto max-w-7xl px-4 py-12 lg:px-8 lg:py-16">
            <h2
                class="text-4xl font-bold leading-tight text-foreground md:text-center lg:text-5xl"
            >
                {title}
            </h2>

            <div class="mt-10 md:hidden">
                <ProductCard product={activeProduct} />

                <div class="mt-12 flex items-center justify-between gap-6">
                    <div class="flex items-center gap-2">
                        {#each randomProducts as product, index (product.id)}
                            <button
                                type="button"
                                class={index === activeProductIndex
                                    ? 'h-2 w-10 rounded-full bg-primary'
                                    : 'size-2.5 rounded-full bg-muted-foreground/30'}
                                aria-label={`${product.name} anzeigen`}
                                aria-current={index === activeProductIndex}
                                onclick={() => (activeProductIndex = index)}
                            ></button>
                        {/each}
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        <button
                            type="button"
                            class="grid size-12 place-items-center rounded-full border bg-white text-foreground shadow-sm transition hover:bg-muted"
                            aria-label="Vorheriges Produkt"
                            onclick={showPreviousProduct}
                        >
                            <ArrowLeft class="size-5" />
                        </button>
                        <button
                            type="button"
                            class="grid size-12 place-items-center rounded-full border bg-white text-foreground shadow-sm transition hover:bg-muted"
                            aria-label="Nächstes Produkt"
                            onclick={showNextProduct}
                        >
                            <ArrowRight class="size-5" />
                        </button>
                    </div>
                </div>
            </div>

            <div
                class="mt-12 hidden gap-x-5 gap-y-10 md:grid md:grid-cols-2 lg:grid-cols-4"
            >
                {#each randomProducts as product (product.id)}
                    <ProductCard {product} />
                {/each}
            </div>
        </div>
    </section>
{/if}
