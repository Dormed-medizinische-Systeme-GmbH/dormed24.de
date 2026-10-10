<script lang="ts">
    import { Badge } from '@/components/ui/badge';
    import { formatDate } from '@/lib/date';
    import type { ArticleEditLayoutProps, ArticleEditPreview } from '@/types';

    let {
        fields,
        preview,
    }: ArticleEditLayoutProps & { preview: ArticleEditPreview } = $props();

    const publishedLabel = $derived(
        preview.publishedAt
            ? formatDate(new Date(preview.publishedAt), { withYear: true })
            : null,
    );
</script>

<!-- Variante E: Formular links, Live-Vorschau des Artikels rechts -->
<div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_26rem]">
    <div class="flex min-w-0 flex-col gap-6">
        {@render fields.title()}
        {@render fields.teaser()}
        {@render fields.content()}
        <div class="grid gap-6 sm:grid-cols-2">
            {@render fields.category()}
            {@render fields.publishedAt()}
        </div>
        {@render fields.slug()}
        {@render fields.cover()}
        {@render fields.seo()}
        {@render fields.meta()}
    </div>

    <aside class="flex flex-col gap-2 xl:sticky xl:top-0">
        <p class="text-xs text-muted-foreground">
            Vorschau (vereinfacht, ungespeicherter Stand)
        </p>
        <article
            class="flex flex-col gap-3 rounded-2xl border bg-white p-6"
            data-testid="article-preview"
        >
            <div class="flex items-center gap-2">
                <span
                    class="text-xs font-bold tracking-wide text-brand-accent uppercase"
                    >{preview.categoryLabel}</span
                >
                {#if !publishedLabel}
                    <Badge variant="secondary">Entwurf</Badge>
                {/if}
            </div>
            <h2 class="text-2xl font-bold text-brand-secondary">
                {preview.title || 'Titel'}
            </h2>
            {#if publishedLabel}
                <p class="text-sm text-muted-foreground">{publishedLabel}</p>
            {/if}
            {#if preview.teaser}
                <p class="text-gray-700">{preview.teaser}</p>
            {/if}
            <p
                class="line-clamp-[14] text-sm whitespace-pre-line text-gray-600"
            >
                {preview.content}
            </p>
        </article>
    </aside>
</div>
