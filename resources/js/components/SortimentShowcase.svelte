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

    $effect(() => {
        if (!carouselApi) {
            return;
        }

        const syncSelectedIndex = () => {
            selectedIndex = carouselApi.selectedScrollSnap();
        };

        carouselApi.on('select', syncSelectedIndex);
        syncSelectedIndex();

        return () => {
            carouselApi.off('select', syncSelectedIndex);
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
                <Carousel.Content>
                    {#each products as product (product.id)}
                        <Carousel.Item class="basis-full md:basis-1/2 lg:basis-1/4">
                            <ProductCard {product} />
                        </Carousel.Item>
                    {/each}
                </Carousel.Content>
            </Carousel.Root>

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
                        class="grid size-12 place-items-center rounded-full border bg-white text-foreground shadow-sm transition hover:bg-muted"
                        aria-label="Vorheriges Produkt"
                        onclick={() => carouselApi?.scrollPrev()}
                    >
                        <ArrowLeft class="size-5" />
                    </button>
                    <button
                        type="button"
                        class="grid size-12 place-items-center rounded-full border bg-white text-foreground shadow-sm transition hover:bg-muted"
                        aria-label="Nächstes Produkt"
                        onclick={() => carouselApi?.scrollNext()}
                    >
                        <ArrowRight class="size-5" />
                    </button>
                </div>
            </div>
        </div>
    </section>
{/if}
