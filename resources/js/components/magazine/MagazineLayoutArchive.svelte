<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { show as magazineShow } from '@/actions/App/Http/Controllers/MagazineController';
    import MagazineEmpty from '@/components/magazine/MagazineEmpty.svelte';
    import MagazineFilters from '@/components/magazine/MagazineFilters.svelte';
    import MagazineIntro from '@/components/magazine/MagazineIntro.svelte';
    import { groupByMonth } from '@/lib/magazine';
    import type { MagazineLayoutProps } from '@/types';

    let { articles, categories, activeCategory }: MagazineLayoutProps =
        $props();

    const groups = $derived(groupByMonth(articles));

    function day(iso: string | null): string {
        return iso ? String(new Date(iso).getDate()).padStart(2, '0') : '–';
    }
</script>

<!-- Variante E: kompaktes Archiv, nach Monat gruppiert -->
<div class="flex flex-col gap-2"><MagazineIntro /></div>
<MagazineFilters {categories} {activeCategory} />

<div class="flex max-w-4xl flex-col gap-10">
    {#each groups as group (group.label)}
        <section aria-label={group.label}>
            <h2
                class="mb-2 text-sm font-bold tracking-wide text-muted-foreground uppercase"
            >
                {group.label}
            </h2>
            <ul class="divide-y border-y">
                {#each group.articles as article (article.slug)}
                    <li>
                        <Link
                            href={magazineShow.url(article.slug)}
                            class="group grid grid-cols-[3rem_minmax(0,1fr)_auto] items-center gap-4 py-3 hover:bg-slate-50"
                        >
                            <span
                                class="text-2xl font-bold text-brand-accent tabular-nums"
                                >{day(article.published_at)}</span
                            >
                            <span class="min-w-0">
                                <span
                                    class="block truncate font-semibold text-brand-secondary group-hover:underline"
                                    >{article.title}</span
                                >
                                {#if article.teaser}
                                    <span
                                        class="block truncate text-sm text-muted-foreground"
                                        >{article.teaser}</span
                                    >
                                {/if}
                            </span>
                            <span
                                class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-gray-700"
                                >{article.category}</span
                            >
                        </Link>
                    </li>
                {/each}
            </ul>
        </section>
    {:else}
        <MagazineEmpty />
    {/each}
</div>
