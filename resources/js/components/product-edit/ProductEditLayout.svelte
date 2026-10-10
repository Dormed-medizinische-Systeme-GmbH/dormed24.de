<script lang="ts">
    import ProductImagesEditor from '@/components/product-edit/ProductImagesEditor.svelte';
    import ProductRatingsList from '@/components/product-edit/ProductRatingsList.svelte';
    import ProductVariantsEditor from '@/components/product-edit/ProductVariantsEditor.svelte';
    import * as Tabs from '@/components/ui/tabs';
    import type { ProductEditLayoutProps } from '@/types';

    let { product, ratings, fields, onsubmit }: ProductEditLayoutProps =
        $props();

    const mainImage = $derived(product.images[0]);
</script>

{#snippet count(n: number)}
    {#if n > 0}
        <span
            class="ml-1.5 rounded-full bg-muted px-1.5 py-0.5 text-xs font-medium tabular-nums"
        >
            {n}
        </span>
    {/if}
{/snippet}

<!-- Galerie links (Hauptbild 1:1 wie im Shop), Kerndaten rechts, Details in Reitern darunter -->
<div class="mx-auto flex w-full max-w-6xl flex-col gap-8">
    <div class="grid gap-8 lg:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">
        <div class="flex flex-col gap-4">
            <div
                class="flex aspect-square items-center justify-center overflow-hidden rounded-xl border bg-muted"
            >
                {#if mainImage}
                    <img
                        src={mainImage.url}
                        alt=""
                        class="size-full object-contain"
                    />
                {:else}
                    <span class="text-sm text-muted-foreground">
                        Noch kein Bild
                    </span>
                {/if}
            </div>
            <ProductImagesEditor
                productId={product.id}
                images={product.images}
            />
        </div>

        <form {onsubmit} class="@container flex min-w-0 flex-col gap-6">
            {@render fields.name()}
            {@render fields.price()}
            <div class="grid gap-6 @lg:grid-cols-2">
                {@render fields.category()}
                {@render fields.manufacturer()}
            </div>
            {@render fields.specialties()}
            {@render fields.availability()}
        </form>
    </div>

    <Tabs.Root value="beschreibung">
        <Tabs.List>
            <Tabs.Trigger value="beschreibung">Beschreibung</Tabs.Trigger>
            <Tabs.Trigger value="technische-daten">
                Technische Daten
            </Tabs.Trigger>
            <Tabs.Trigger value="variationen">
                Variationen
                {@render count(product.variants.length)}
            </Tabs.Trigger>
            <Tabs.Trigger value="bewertungen">
                Bewertungen
                {@render count(ratings.length)}
            </Tabs.Trigger>
        </Tabs.List>

        <Tabs.Content value="beschreibung">
            <form {onsubmit} class="mt-6 max-w-2xl">
                {@render fields.description()}
            </form>
        </Tabs.Content>
        <Tabs.Content value="technische-daten">
            <div class="mt-6 max-w-2xl">{@render fields.specs()}</div>
        </Tabs.Content>
        <Tabs.Content value="variationen">
            <div class="mt-6 max-w-2xl">
                <ProductVariantsEditor
                    productId={product.id}
                    variants={product.variants}
                />
            </div>
        </Tabs.Content>
        <Tabs.Content value="bewertungen">
            <div class="mt-6 max-w-2xl">
                <ProductRatingsList {ratings} />
            </div>
        </Tabs.Content>
    </Tabs.Root>
</div>
