<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { show as magazineShow } from '@/actions/App/Http/Controllers/MagazineController';
    import MagazineEmpty from '@/components/magazine/MagazineEmpty.svelte';
    import MagazineFilters from '@/components/magazine/MagazineFilters.svelte';
    import MagazineIntro from '@/components/magazine/MagazineIntro.svelte';
    import { formatDate } from '@/lib/date';
    import { cn } from '@/lib/utils';
    import type { MagazineLayoutProps } from '@/types';

    let { articles, categories, activeCategory }: MagazineLayoutProps =
        $props();

    // Kachel-Größe und Farbe wiederholen sich im Muster: der erste Beitrag ist 2×2.
    const tones = [
        'bg-brand-secondary text-white',
        'bg-[#e8eef6] text-brand-secondary',
        'bg-slate-100 text-brand-secondary',
        'bg-brand-accent/15 text-brand-secondary',
    ];

    function tileClass(index: number): string {
        if (index === 0) {
            return 'sm:col-span-2 sm:row-span-2';
        }

        return index % 5 === 3 ? 'lg:col-span-2' : '';
    }
</script>

<!-- Variante C: Mosaik – Kacheln unterschiedlicher Größe, erster Beitrag groß -->
<div class="flex flex-wrap items-end justify-between gap-4">
    <div class="flex flex-col gap-2"><MagazineIntro /></div>
    <MagazineFilters {categories} {activeCategory} />
</div>

{#if articles.length > 0}
    <div
        class="grid auto-rows-[14rem] grid-flow-dense gap-4 sm:grid-cols-2 lg:grid-cols-4"
    >
        {#each articles as article, index (article.slug)}
            <article
                class={cn(
                    'group relative flex flex-col justify-end gap-2 overflow-hidden rounded-2xl p-6',
                    tones[index % tones.length],
                    tileClass(index),
                )}
            >
                <span
                    class="text-xs font-bold tracking-wide uppercase opacity-80"
                    >{article.category}</span
                >
                <h2
                    class={cn(
                        'leading-snug font-bold',
                        index === 0 ? 'text-3xl' : 'text-lg',
                    )}
                >
                    <Link
                        href={magazineShow.url(article.slug)}
                        class="after:absolute after:inset-0 hover:underline"
                        >{article.title}</Link
                    >
                </h2>
                {#if article.teaser}
                    <p
                        class={cn(
                            'text-sm opacity-80',
                            index === 0 ? 'line-clamp-3' : 'line-clamp-2',
                        )}
                    >
                        {article.teaser}
                    </p>
                {/if}
                {#if article.published_at}
                    <p class="text-xs opacity-70">
                        {formatDate(article.published_at, { withYear: true })}
                    </p>
                {/if}
            </article>
        {/each}
    </div>
{:else}
    <MagazineEmpty />
{/if}
