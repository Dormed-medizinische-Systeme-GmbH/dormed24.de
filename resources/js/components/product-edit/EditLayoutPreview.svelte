<script lang="ts">
    import ProductImagesEditor from '@/components/product-edit/ProductImagesEditor.svelte';
    import ProductRatingsList from '@/components/product-edit/ProductRatingsList.svelte';
    import ProductVariantsEditor from '@/components/product-edit/ProductVariantsEditor.svelte';
    import ProductCard from '@/components/ProductCard.svelte';
    import { Separator } from '@/components/ui/separator';
    import * as Tabs from '@/components/ui/tabs';
    import { formatPrice } from '@/lib/currency';
    import type { ProductEditLayoutProps, ProductEditPreview } from '@/types';

    let {
        product,
        ratings,
        fields,
        onsubmit,
        preview,
    }: ProductEditLayoutProps & { preview: ProductEditPreview } = $props();

    const defaultVariant = $derived(
        product.variants.find((variant) => variant.is_default) ??
            product.variants[0],
    );
    const shownPrice = $derived(defaultVariant?.price ?? preview.price);
    const filledSpecs = $derived(
        preview.specs.filter((spec) => spec.label.trim() || spec.value.trim()),
    );
    const averageStars = $derived(
        ratings.length
            ? ratings.reduce((sum, rating) => sum + rating.stars, 0) /
                  ratings.length
            : null,
    );
    const cardProduct = $derived({
        id: product.id,
        name: preview.name,
        description: preview.description,
        price: shownPrice,
        is_available: preview.isAvailable,
        manufacturer: preview.manufacturer,
        images: product.images,
        ratings_avg_stars: averageStars,
        ratings_count: ratings.length,
    });
</script>

<!-- Variante E: Formular links, Live-Vorschau des Shops rechts -->
<div
    class="mx-auto grid w-full max-w-7xl items-start gap-6 xl:grid-cols-[minmax(0,1fr)_26rem]"
>
    <div class="flex min-w-0 flex-col gap-8">
        <section class="flex flex-col gap-6">
            <h2 class="text-base font-semibold">Stammdaten</h2>
            <form {onsubmit} class="flex flex-col gap-6">
                {@render fields.name()}
                <div class="grid gap-6 sm:grid-cols-[10rem_1fr_1fr]">
                    {@render fields.price()}
                    {@render fields.category()}
                    {@render fields.manufacturer()}
                </div>
                {@render fields.specialties()}
                {@render fields.description()}
            </form>
        </section>

        <Separator />
        {@render fields.specs()}
        <Separator />
        <ProductImagesEditor
            productId={product.id}
            images={product.images}
            columns={4}
        />
        <Separator />
        <ProductVariantsEditor
            productId={product.id}
            variants={product.variants}
        />
        <Separator />
        {@render fields.availability()}
        <ProductRatingsList {ratings} />
    </div>

    <aside class="flex min-w-0 flex-col gap-3 xl:sticky xl:top-0">
        <Tabs.Root value="karte">
            <div class="flex items-center justify-between">
                <Tabs.List>
                    <Tabs.Trigger value="karte">Produktkarte</Tabs.Trigger>
                    <Tabs.Trigger value="detail">Detailseite</Tabs.Trigger>
                </Tabs.List>
                <span class="text-xs text-muted-foreground">live</span>
            </div>

            <Tabs.Content value="karte">
                <div class="mt-3 rounded-xl border bg-muted p-4">
                    <div class="pointer-events-none mx-auto max-w-72" inert>
                        <ProductCard product={cardProduct} />
                    </div>
                </div>
            </Tabs.Content>

            <Tabs.Content value="detail">
                <div
                    class="mt-3 flex flex-col gap-4 rounded-xl border bg-card p-4"
                    data-testid="detail-preview"
                >
                    {#if product.images[0]}
                        <img
                            src={product.images[0].url}
                            alt=""
                            class="aspect-square w-full rounded-lg border object-contain"
                        />
                    {/if}
                    <div>
                        <p class="text-xs text-muted-foreground">
                            {preview.manufacturer?.name ?? 'Hersteller'} · {preview
                                .category?.name ?? 'Kategorie'}
                        </p>
                        <h3 class="text-lg font-semibold">
                            {preview.name || 'Produktname'}
                        </h3>
                    </div>
                    <p class="text-2xl font-semibold tabular-nums">
                        {formatPrice(shownPrice)}
                    </p>
                    {#if product.variants.length > 0}
                        <div class="flex flex-wrap gap-2">
                            {#each product.variants as variant (variant.id)}
                                <span
                                    class="rounded-md border px-2.5 py-1 text-xs {variant.id ===
                                    defaultVariant?.id
                                        ? 'border-primary font-medium ring-1 ring-primary'
                                        : ''}"
                                >
                                    {variant.label} · {formatPrice(
                                        variant.price,
                                    )}
                                </span>
                            {/each}
                        </div>
                    {/if}
                    <p
                        class="line-clamp-6 text-sm whitespace-pre-line text-muted-foreground"
                    >
                        {preview.description}
                    </p>
                    {#if filledSpecs.length > 0}
                        <dl class="divide-y rounded-lg border text-sm">
                            {#each filledSpecs as spec, index (index)}
                                <div class="flex gap-3 px-3 py-1.5">
                                    <dt class="w-2/5 text-muted-foreground">
                                        {spec.label}
                                    </dt>
                                    <dd class="font-medium">{spec.value}</dd>
                                </div>
                            {/each}
                        </dl>
                    {/if}
                </div>
            </Tabs.Content>
        </Tabs.Root>
        <p class="text-xs text-muted-foreground">
            Vereinfachte Vorschau der ungespeicherten Formulardaten.
        </p>
    </aside>
</div>
