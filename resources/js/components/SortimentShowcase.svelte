<script lang="ts">
    import ArrowLeft from '@lucide/svelte/icons/arrow-left';
    import ArrowRight from '@lucide/svelte/icons/arrow-right';
    import ProductCard from '@/components/ProductCard.svelte';
    import * as Carousel from '@/components/ui/carousel';

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
        products = [],
    }: {
        title?: string;
        products?: ShowcaseProduct[];
    } = $props();

    let carouselApi = $state<any>();
    let selectedIndex = $state(0);
    let canScrollPrev = $state(false);
    let canScrollNext = $state(false);

    $effect(() => {
        if (!carouselApi) {
            return;
        }

        const syncCarouselState = () => {
            selectedIndex = carouselApi.selectedScrollSnap();
            canScrollPrev = carouselApi.canScrollPrev();
            canScrollNext = carouselApi.canScrollNext();
        };

        carouselApi.on('select', syncCarouselState);
        carouselApi.on('reInit', syncCarouselState);
        syncCarouselState();

        return () => {
            carouselApi.off('select', syncCarouselState);
            carouselApi.off('reInit', syncCarouselState);
        };
    });
</script>

{#if products.length > 0}
    <section class="bg-muted">
        <div class="mx-auto max-w-7xl px-4 py-12 lg:px-8 lg:py-16">
            <h2
                class="text-4xl font-bold leading-tight text-foreground md:text-center lg:text-5xl"
            >
                {title}
            </h2>

            <Carousel.Root
                class="mt-10 w-full"
                setApi={(api) => {
                    carouselApi = api;
                }}
            >
                <!-- pb-6/pt-3: Platz für Hover-Schatten und -Anhebung der Karten,
                     sonst schneidet das overflow-hidden des Karussells sie ab. -->
                <Carousel.Content class="pb-6 pt-3">
                    {#each products as product (product.id)}
                        <Carousel.Item
                            class="basis-full md:basis-1/2 lg:basis-1/4"
                        >
                            <ProductCard {product} />
                        </Carousel.Item>
                    {/each}
                </Carousel.Content>
            </Carousel.Root>

            {#if canScrollPrev || canScrollNext}
                <div class="mt-8 flex items-center justify-between gap-6">
                    <div class="flex flex-wrap items-center gap-2">
                        {#each products as product, index (product.id)}
                            <button
                                type="button"
                                class={index === selectedIndex
                                    ? 'h-2 w-10 rounded-full bg-primary'
                                    : 'size-2.5 rounded-full bg-muted-foreground/30'}
                                aria-label={`${product.name} anzeigen`}
                                aria-current={index === selectedIndex}
                                onclick={() => carouselApi?.scrollTo(index)}
                            ></button>
                        {/each}
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        <button
                            type="button"
                            class="grid size-12 place-items-center rounded-full border bg-white text-foreground shadow-sm transition hover:bg-muted disabled:pointer-events-none disabled:opacity-40"
                            aria-label="Vorheriges Produkt"
                            disabled={!canScrollPrev}
                            onclick={() => carouselApi?.scrollPrev()}
                        >
                            <ArrowLeft class="size-5" />
                        </button>
                        <button
                            type="button"
                            class="grid size-12 place-items-center rounded-full border bg-white text-foreground shadow-sm transition hover:bg-muted disabled:pointer-events-none disabled:opacity-40"
                            aria-label="Nächstes Produkt"
                            disabled={!canScrollNext}
                            onclick={() => carouselApi?.scrollNext()}
                        >
                            <ArrowRight class="size-5" />
                        </button>
                    </div>
                </div>
            {/if}
        </div>
    </section>
{/if}
