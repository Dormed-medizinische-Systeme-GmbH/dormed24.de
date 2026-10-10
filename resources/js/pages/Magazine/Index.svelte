<script lang="ts">
    import AppFooter from '@/components/AppFooter.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import MagazineLayoutArchive from '@/components/magazine/MagazineLayoutArchive.svelte';
    import MagazineLayoutCategories from '@/components/magazine/MagazineLayoutCategories.svelte';
    import MagazineLayoutHighlight from '@/components/magazine/MagazineLayoutHighlight.svelte';
    import MagazineLayoutList from '@/components/magazine/MagazineLayoutList.svelte';
    import MagazineLayoutMosaic from '@/components/magazine/MagazineLayoutMosaic.svelte';
    import ShopHeader from '@/components/ShopHeader.svelte';
    import VariantSwitcher from '@/components/VariantSwitcher.svelte';
    import { MAGAZINE_VARIANTS } from '@/lib/magazine';
    import { useLayoutVariant } from '@/lib/useLayoutVariant.svelte';
    import type { ArticleTeaser } from '@/types';

    let {
        articles,
        categories,
        activeCategory,
    }: {
        articles: ArticleTeaser[];
        categories: Record<string, string>;
        activeCategory: string | null;
    } = $props();

    // Layout-Variante: Auswahl steht in der URL (?variant=B); ohne Parameter gilt A.
    const layoutVariant = useLayoutVariant(MAGAZINE_VARIANTS);
</script>

<AppHead
    title="Magazin"
    description="Kaufberatung, Neuigkeiten und Praxiswissen rund um Medizintechnik."
>
    <!-- Noch nicht verlinkt: nicht in Suchmaschinen aufnehmen. -->
    <meta name="robots" content="noindex, nofollow" />
</AppHead>

<div class="flex min-h-screen flex-col bg-white">
    <ShopHeader />

    <main
        class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-8 px-4 py-10 pb-24 lg:px-8"
    >
        {#if layoutVariant.current === 'B'}
            <MagazineLayoutList {articles} {categories} {activeCategory} />
        {:else if layoutVariant.current === 'C'}
            <MagazineLayoutMosaic {articles} {categories} {activeCategory} />
        {:else if layoutVariant.current === 'D'}
            <MagazineLayoutCategories
                {articles}
                {categories}
                {activeCategory}
            />
        {:else if layoutVariant.current === 'E'}
            <MagazineLayoutArchive {articles} {categories} {activeCategory} />
        {:else}
            <MagazineLayoutHighlight {articles} {categories} {activeCategory} />
        {/if}
    </main>

    <AppFooter />
</div>

<VariantSwitcher
    options={MAGAZINE_VARIANTS}
    current={layoutVariant.current}
    onselect={layoutVariant.select}
/>
