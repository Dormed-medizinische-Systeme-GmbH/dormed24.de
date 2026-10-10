<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { show as magazineShow } from '@/actions/App/Http/Controllers/MagazineController';
    import MagazineEmpty from '@/components/magazine/MagazineEmpty.svelte';
    import MagazineFilters from '@/components/magazine/MagazineFilters.svelte';
    import MagazineIntro from '@/components/magazine/MagazineIntro.svelte';
    import { formatDate } from '@/lib/date';
    import type { MagazineLayoutProps } from '@/types';

    let { articles, categories, activeCategory }: MagazineLayoutProps =
        $props();

    const [featured, ...rest] = $derived(articles);
</script>

<!-- Variante A: Highlight – ein großer Beitrag, darunter Raster (bisheriges Layout) -->
<div class="flex flex-wrap items-end justify-between gap-4">
    <div class="flex max-w-2xl flex-col gap-2">
        <MagazineIntro />
    </div>
    <MagazineFilters {categories} {activeCategory} />
</div>

{#if featured}
    <article
        class="grid overflow-hidden rounded-2xl border bg-slate-50 md:grid-cols-2"
    >
        <div
            class="flex min-h-56 items-center justify-center bg-[#e8eef6] text-sm text-muted-foreground"
            aria-hidden="true"
        >
            dormed24 Magazin
        </div>
        <div class="flex flex-col gap-3 p-6 lg:p-8">
            <span
                class="text-xs font-bold tracking-wide text-brand-accent uppercase"
                >{featured.category}</span
            >
            <h2 class="text-2xl font-bold text-brand-secondary">
                <Link
                    href={magazineShow.url(featured.slug)}
                    class="hover:underline">{featured.title}</Link
                >
            </h2>
            {#if featured.teaser}
                <p class="text-gray-700">{featured.teaser}</p>
            {/if}
            {#if featured.published_at}
                <p class="text-sm text-muted-foreground">
                    {formatDate(featured.published_at, { withYear: true })}
                </p>
            {/if}
            <Link
                href={magazineShow.url(featured.slug)}
                class="self-start font-semibold text-brand-accent hover:underline"
            >
                Weiterlesen →
            </Link>
        </div>
    </article>
{:else}
    <MagazineEmpty />
{/if}

{#if rest.length > 0}
    <section
        aria-label="Weitere Beiträge"
        class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3"
    >
        {#each rest as article (article.slug)}
            <article class="flex flex-col gap-2 rounded-xl border p-5">
                <span
                    class="text-xs font-bold tracking-wide text-brand-accent uppercase"
                    >{article.category}</span
                >
                <h3 class="text-lg font-semibold text-brand-secondary">
                    <Link
                        href={magazineShow.url(article.slug)}
                        class="hover:underline">{article.title}</Link
                    >
                </h3>
                {#if article.teaser}
                    <p class="text-sm text-gray-600">{article.teaser}</p>
                {/if}
                {#if article.published_at}
                    <p class="mt-auto text-xs text-muted-foreground">
                        {formatDate(article.published_at, { withYear: true })}
                    </p>
                {/if}
            </article>
        {/each}
    </section>
{/if}
