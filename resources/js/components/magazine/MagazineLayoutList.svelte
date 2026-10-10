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
</script>

<!-- Variante B: Redaktionelle Liste, Kategorien als feste Leiste links -->
<div class="flex max-w-2xl flex-col gap-2">
    <MagazineIntro />
</div>

<div class="grid gap-10 lg:grid-cols-[13rem_minmax(0,1fr)]">
    <aside class="lg:sticky lg:top-24 lg:self-start">
        <p
            class="mb-3 text-xs font-bold tracking-wide text-muted-foreground uppercase"
        >
            Kategorien
        </p>
        <MagazineFilters {categories} {activeCategory} orientation="column" />
    </aside>

    <div class="flex min-w-0 flex-col">
        {#each articles as article (article.slug)}
            <article
                class="grid gap-2 border-b py-7 first:pt-0 sm:grid-cols-[7rem_minmax(0,1fr)] sm:gap-6"
            >
                <p class="text-sm text-muted-foreground sm:pt-1">
                    {#if article.published_at}
                        {formatDate(article.published_at, { withYear: true })}
                    {/if}
                </p>
                <div class="flex flex-col gap-2">
                    <span
                        class="text-xs font-bold tracking-wide text-brand-accent uppercase"
                        >{article.category}</span
                    >
                    <h2 class="text-2xl font-bold text-brand-secondary">
                        <Link
                            href={magazineShow.url(article.slug)}
                            class="hover:underline">{article.title}</Link
                        >
                    </h2>
                    {#if article.teaser}
                        <p class="text-gray-700">{article.teaser}</p>
                    {/if}
                    <Link
                        href={magazineShow.url(article.slug)}
                        class="self-start text-sm font-semibold text-brand-accent hover:underline"
                    >
                        Weiterlesen →
                    </Link>
                </div>
            </article>
        {:else}
            <MagazineEmpty />
        {/each}
    </div>
</div>
