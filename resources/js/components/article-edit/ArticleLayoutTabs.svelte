<script lang="ts">
    import * as Tabs from '@/components/ui/tabs';
    import type { ArticleEditLayoutProps } from '@/types';

    let { fields, errors }: ArticleEditLayoutProps = $props();

    // Fehler in einem nicht aktiven Reiter sichtbar machen.
    const hasError = (keys: string[]) => keys.some((key) => errors[key]);
</script>

{#snippet errorDot(show: boolean)}
    {#if show}
        <span
            class="ml-1.5 size-2 rounded-full bg-destructive"
            role="img"
            aria-label="Enthält Fehler"
        ></span>
    {/if}
{/snippet}

<!-- Variante B: Inhalt, Veröffentlichung und Vorbereitetes in Reitern -->
<Tabs.Root value="inhalt">
    <Tabs.List>
        <Tabs.Trigger value="inhalt">
            Inhalt
            {@render errorDot(hasError(['title', 'teaser', 'content']))}
        </Tabs.Trigger>
        <Tabs.Trigger value="veroeffentlichung">
            Veröffentlichung
            {@render errorDot(hasError(['slug', 'category', 'published_at']))}
        </Tabs.Trigger>
        <Tabs.Trigger value="seo">SEO &amp; Medien</Tabs.Trigger>
    </Tabs.List>

    <Tabs.Content value="inhalt">
        <div class="mt-6 flex max-w-3xl flex-col gap-6">
            {@render fields.title()}
            {@render fields.teaser()}
            {@render fields.content()}
        </div>
    </Tabs.Content>

    <Tabs.Content value="veroeffentlichung">
        <div class="mt-6 flex max-w-xl flex-col gap-6">
            {@render fields.slug()}
            {@render fields.category()}
            {@render fields.publishedAt()}
        </div>
    </Tabs.Content>

    <Tabs.Content value="seo">
        <div class="mt-6 flex max-w-xl flex-col gap-6">
            {@render fields.cover()}
            {@render fields.seo()}
            {@render fields.meta()}
        </div>
    </Tabs.Content>
</Tabs.Root>
