<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import {
        index as magazineIndex,
        show as magazineShow,
    } from '@/actions/App/Http/Controllers/MagazineController';
    import AppFooter from '@/components/AppFooter.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ShopHeader from '@/components/ShopHeader.svelte';
    import { formatDate } from '@/lib/date';

    type ArticleTeaser = {
        title: string;
        slug: string;
        teaser: string | null;
        category: string;
        published_at: string | null;
    };

    let {
        articles,
        categories,
        activeCategory,
    }: {
        articles: ArticleTeaser[];
        categories: Record<string, string>;
        activeCategory: string | null;
    } = $props();

    const filters = $derived([
        { key: null, label: 'Alle' },
        ...Object.entries(categories).map(([key, label]) => ({ key, label })),
    ]);

    const [featured, ...rest] = $derived(articles);

    function filterUrl(key: string | null): string {
        return magazineIndex.url(key ? { query: { kategorie: key } } : {});
    }
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
        class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-8 px-4 py-10 lg:px-8"
    >
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex max-w-2xl flex-col gap-2">
                <h1 class="text-3xl font-bold text-brand-secondary lg:text-4xl">
                    Magazin
                </h1>
                <p class="text-muted-foreground">
                    Kaufberatung, Neuigkeiten und Praxiswissen rund um
                    Medizintechnik.
                </p>
            </div>
            <nav aria-label="Kategorien" class="flex flex-wrap gap-2">
                {#each filters as filter (filter.key ?? 'all')}
                    <Link
                        href={filterUrl(filter.key)}
                        preserveScroll
                        aria-current={filter.key === activeCategory
                            ? 'page'
                            : undefined}
                        class="rounded-full border px-4 py-2 text-sm font-medium transition-colors {filter.key ===
                        activeCategory
                            ? 'border-brand-secondary bg-brand-secondary text-white'
                            : 'border-gray-300 text-gray-700 hover:border-brand-accent'}"
                    >
                        {filter.label}
                    </Link>
                {/each}
            </nav>
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
                            {formatDate(featured.published_at, {
                                withYear: true,
                            })}
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
            <p
                class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
            >
                Hier erscheinen bald die ersten Beiträge.
            </p>
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
                            <p class="text-sm text-gray-600">
                                {article.teaser}
                            </p>
                        {/if}
                        {#if article.published_at}
                            <p class="mt-auto text-xs text-muted-foreground">
                                {formatDate(article.published_at, {
                                    withYear: true,
                                })}
                            </p>
                        {/if}
                    </article>
                {/each}
            </section>
        {/if}
    </main>

    <AppFooter />
</div>
