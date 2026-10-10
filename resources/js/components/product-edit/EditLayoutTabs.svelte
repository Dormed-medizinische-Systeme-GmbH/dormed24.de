<script lang="ts">
    import ProductImagesEditor from '@/components/product-edit/ProductImagesEditor.svelte';
    import ProductRatingsList from '@/components/product-edit/ProductRatingsList.svelte';
    import ProductVariantsEditor from '@/components/product-edit/ProductVariantsEditor.svelte';
    import * as Tabs from '@/components/ui/tabs';
    import type { ProductEditLayoutProps } from '@/types';

    let { product, ratings, fields, onsubmit }: ProductEditLayoutProps =
        $props();
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

<!-- Variante A: Reiter (bisheriges Layout) -->
<Tabs.Root value="stammdaten">
    <Tabs.List>
        <Tabs.Trigger value="stammdaten">Stammdaten</Tabs.Trigger>
        <Tabs.Trigger value="bilder">
            Bilder
            {@render count(product.images.length)}
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

    <Tabs.Content value="stammdaten">
        <form {onsubmit} class="mt-6 flex max-w-2xl flex-col gap-6">
            {@render fields.name()}
            {@render fields.price()}
            {@render fields.category()}
            {@render fields.specialties()}
            {@render fields.manufacturer()}
            {@render fields.description()}
            {@render fields.specs()}
            {@render fields.availability()}
            {@render fields.actions()}
        </form>
    </Tabs.Content>

    <Tabs.Content value="bilder">
        <div class="mt-6 max-w-2xl">
            <ProductImagesEditor
                productId={product.id}
                images={product.images}
            />
        </div>
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
