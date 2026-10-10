<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { show as magazineShow } from '@/actions/App/Http/Controllers/MagazineController';
    import MagazineEmpty from '@/components/magazine/MagazineEmpty.svelte';
    import MagazineFilters from '@/components/magazine/MagazineFilters.svelte';
    import MagazineIntro from '@/components/magazine/MagazineIntro.svelte';
    import { formatDate } from '@/lib/date';
    import { groupByCategory } from '@/lib/magazine';
    import type { MagazineLayoutProps } from '@/types';

    let { articles, categories, activeCategory }: MagazineLayoutProps =
        $props();

    const groups = $derived(groupByCategory(articles));
</script>

<!-- Variante D: nach Kategorie gruppiert, je Kategorie eine horizontale Reihe -->
<div class="flex flex-col gap-2"><MagazineIntro /></div>
<MagazineFilters {categories} {activeCategory} />

{#each groups as group (group.label)}
    <section class="flex flex-col gap-4" aria-label={group.label}>
        <div class="flex items-baseline justify-between gap-4 border-b pb-2">
            <h2 class="text-xl font-bold text-brand-secondary">
                {group.label}
            </h2>
            <span class="text-sm text-muted-foreground"
                >{group.articles.length}
                {group.articles.length === 1 ? 'Beitrag' : 'Beiträge'}</span
            >
        </div>
        <div
            class="-mx-4 flex snap-x gap-4 overflow-x-auto px-4 pb-2 lg:-mx-8 lg:px-8"
        >
            {#each group.articles as article (article.slug)}
                <article
                    class="flex w-72 shrink-0 snap-start flex-col gap-2 rounded-xl border border-t-4 border-t-brand-accent p-5 sm:w-80"
                >
                    <h3 class="text-lg font-semibold text-brand-secondary">
                        <Link
                            href={magazineShow.url(article.slug)}
                            class="hover:underline">{article.title}</Link
                        >
                    </h3>
                    {#if article.teaser}
                        <p class="line-clamp-3 text-sm text-gray-600">
                            {article.teaser}
                        </p>
                    {/if}
                    {#if article.published_at}
                        <p class="mt-auto pt-2 text-xs text-muted-foreground">
                            {formatDate(article.published_at, {
                                withYear: true,
                            })}
                        </p>
                    {/if}
                </article>
            {/each}
        </div>
    </section>
{:else}
    <MagazineEmpty />
{/each}
