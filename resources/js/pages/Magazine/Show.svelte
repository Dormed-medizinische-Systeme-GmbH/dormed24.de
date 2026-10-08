<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { index as magazineIndex } from '@/actions/App/Http/Controllers/MagazineController';
    import AppFooter from '@/components/AppFooter.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ShopHeader from '@/components/ShopHeader.svelte';
    import { formatDate } from '@/lib/date';

    let {
        article,
    }: {
        article: {
            title: string;
            teaser: string | null;
            category: string;
            published_at: string | null;
            /** Serverseitig aus Markdown erzeugt und bereinigt. */
            html: string;
        };
    } = $props();
</script>

<AppHead title={article.title} description={article.teaser} ogType="article">
    <!-- Noch nicht verlinkt: nicht in Suchmaschinen aufnehmen. -->
    <meta name="robots" content="noindex, nofollow" />
</AppHead>

<div class="flex min-h-screen flex-col bg-white">
    <ShopHeader />

    <main
        class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-8 px-4 py-10 lg:px-8"
    >
        <nav
            aria-label="Brotkrumen"
            class="flex flex-wrap gap-2 text-sm text-muted-foreground"
        >
            <Link href="/" class="hover:underline">Startseite</Link>
            <span aria-hidden="true">›</span>
            <Link href={magazineIndex.url()} class="hover:underline"
                >Magazin</Link
            >
            <span aria-hidden="true">›</span>
            <span class="text-gray-900">{article.title}</span>
        </nav>

        <article class="mx-auto flex w-full max-w-3xl flex-col gap-6">
            <header class="flex flex-col gap-3">
                <span
                    class="text-xs font-bold tracking-wide text-brand-accent uppercase"
                    >{article.category}</span
                >
                <h1
                    class="text-3xl leading-tight font-bold text-brand-secondary lg:text-4xl"
                >
                    {article.title}
                </h1>
                {#if article.teaser}
                    <p class="text-lg text-gray-700">{article.teaser}</p>
                {/if}
                {#if article.published_at}
                    <p class="text-sm text-muted-foreground">
                        {formatDate(article.published_at, { withYear: true })}
                    </p>
                {/if}
            </header>

            <div class="article-content">
                <!-- eslint-disable-next-line svelte/no-at-html-tags -- serverseitig aus Markdown erzeugt, eingebettetes HTML entfernt -->
                {@html article.html}
            </div>

            <Link
                href={magazineIndex.url()}
                class="self-start font-semibold text-brand-accent hover:underline"
            >
                ← Zurück zum Magazin
            </Link>
        </article>
    </main>

    <AppFooter />
</div>

<style>
    .article-content {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        font-size: 1.0625rem;
        line-height: 1.75;
        color: #1f2937;
    }

    .article-content :global(h2) {
        margin-top: 1rem;
        font-size: 1.5rem;
        line-height: 1.3;
        font-weight: 700;
        color: var(--color-brand-secondary);
    }

    .article-content :global(h3) {
        margin-top: 0.5rem;
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--color-brand-secondary);
    }

    .article-content :global(ul),
    .article-content :global(ol) {
        padding-left: 1.5rem;
    }

    .article-content :global(ul) {
        list-style: disc;
    }

    .article-content :global(ol) {
        list-style: decimal;
    }

    .article-content :global(a) {
        color: var(--color-brand-accent);
        text-decoration: underline;
    }

    .article-content :global(blockquote) {
        border-left: 3px solid var(--color-brand-accent);
        padding-left: 1rem;
        color: #4b5563;
    }
</style>
