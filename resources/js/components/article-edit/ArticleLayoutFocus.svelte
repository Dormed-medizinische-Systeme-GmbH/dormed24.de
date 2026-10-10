<script lang="ts">
    import type { ArticleEditLayoutProps } from '@/types';

    let { fields, errors }: ArticleEditLayoutProps = $props();
</script>

<!-- Variante C: Schreibfokus – Titel und Text zentral, Einstellungen klappbar -->
<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div class="grid gap-6 sm:grid-cols-2">
        {@render fields.category()}
        {@render fields.publishedAt()}
    </div>

    {@render fields.title(true)}
    {@render fields.teaser()}
    {@render fields.content()}

    <details class="rounded-lg border p-4" open={!!errors.slug}>
        <summary class="cursor-pointer text-sm font-medium">
            Adresse (Slug)
        </summary>
        <div class="mt-4">{@render fields.slug()}</div>
    </details>

    <details class="rounded-lg border p-4">
        <summary class="cursor-pointer text-sm font-medium">
            Später verfügbar: Titelbild, SEO, Autor
        </summary>
        <div class="mt-4 flex flex-col gap-6">
            {@render fields.cover()}
            {@render fields.seo()}
            {@render fields.meta()}
        </div>
    </details>
</div>
