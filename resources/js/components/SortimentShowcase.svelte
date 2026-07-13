<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ArrowLeft from '@lucide/svelte/icons/arrow-left';
    import ArrowRight from '@lucide/svelte/icons/arrow-right';
    import ImageIcon from '@lucide/svelte/icons/image';
    import * as ProductController from '@/actions/App/Http/Controllers/ProductController';
    import { formatPrice } from '@/lib/currency';

    type RandomProduct = {
        id: number;
        name: string;
        price: string | null;
        image_url: string | null;
    };

    let {
        title = 'Entdecken Sie unser Sortiment',
        randomProducts = [],
    }: {
        title?: string;
        randomProducts?: RandomProduct[];
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

    function displayProductPrice(price: string | null): string {
        return price === null ? 'Preis auf Anfrage' : formatPrice(price);
    }
</script>

{#if randomProducts.length > 0 && activeProduct}
    <section class="bg-white">
        <div class="mx-auto max-w-7xl px-4 py-12 lg:px-8 lg:py-16">
            <h2
                class="text-4xl font-bold leading-tight text-foreground md:text-center lg:text-5xl"
            >
                {title}
            </h2>

            <div class="mt-10 md:hidden">
                <Link
                    href={ProductController.show.url(activeProduct)}
                    class="block text-center"
                >
                    <div
                        class="relative grid aspect-square place-items-center overflow-hidden rounded-xl bg-[#e9e9e9]"
                    >
                        {#if activeProduct.image_url}
                            <img
                                src={activeProduct.image_url}
                                alt={activeProduct.name}
                                class="size-full object-cover object-center"
                            />
                        {:else}
                            <div
                                class="relative grid size-24 place-items-center rounded-full border border-border bg-[#ededed]"
                            >
                                <span
                                    class="absolute left-1/2 top-1/2 h-px w-36 -translate-x-1/2 -translate-y-1/2 rotate-45 bg-muted"
                                ></span>
                                <span
                                    class="absolute left-1/2 top-1/2 h-px w-36 -translate-x-1/2 -translate-y-1/2 -rotate-45 bg-muted"
                                ></span>
                                <ImageIcon
                                    class="relative size-4 text-muted-foreground"
                                />
                            </div>
                        {/if}
                    </div>

                    <h3 class="mt-5 text-base font-bold text-foreground">
                        {activeProduct.name}
                    </h3>
                    <p class="mt-3 text-2xl font-bold text-foreground">
                        {displayProductPrice(activeProduct.price)}
                    </p>
                </Link>

                <div class="mt-12 flex items-center justify-between gap-6">
                    <div class="flex items-center gap-2">
                        {#each randomProducts as product, index (product.id)}
                            <button
                                type="button"
                                class={index === activeProductIndex
                                    ? 'h-2 w-10 rounded-full bg-primary'
                                    : 'size-2.5 rounded-full bg-muted'}
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
                    <Link
                        href={ProductController.show.url(product)}
                        class="text-center"
                    >
                        <div
                            class="relative grid aspect-square place-items-center overflow-hidden rounded-xl bg-[#e9e9e9]"
                        >
                            {#if product.image_url}
                                <img
                                    src={product.image_url}
                                    alt={product.name}
                                    class="size-full object-cover object-center transition duration-300 hover:scale-105"
                                />
                            {:else}
                                <div
                                    class="relative grid size-16 place-items-center rounded-full border border-border bg-[#ededed]"
                                >
                                    <span
                                        class="absolute left-1/2 top-1/2 h-px w-24 -translate-x-1/2 -translate-y-1/2 rotate-45 bg-muted"
                                    ></span>
                                    <span
                                        class="absolute left-1/2 top-1/2 h-px w-24 -translate-x-1/2 -translate-y-1/2 -rotate-45 bg-muted"
                                    ></span>
                                    <ImageIcon
                                        class="relative size-3.5 text-muted-foreground"
                                    />
                                </div>
                            {/if}
                        </div>

                        <h3 class="mt-5 text-base font-bold text-foreground">
                            {product.name}
                        </h3>
                        <p class="mt-3 text-2xl font-bold text-foreground">
                            {displayProductPrice(product.price)}
                        </p>
                    </Link>
                {/each}
            </div>
        </div>
    </section>
{/if}
