<script lang="ts">
    import ArticleLayoutFocus from '@/components/article-edit/ArticleLayoutFocus.svelte';
    import ArticleLayoutPreview from '@/components/article-edit/ArticleLayoutPreview.svelte';
    import ArticleLayoutSections from '@/components/article-edit/ArticleLayoutSections.svelte';
    import ArticleLayoutSidebar from '@/components/article-edit/ArticleLayoutSidebar.svelte';
    import ArticleLayoutTabs from '@/components/article-edit/ArticleLayoutTabs.svelte';
    import DisabledField from '@/components/DisabledField.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        NativeSelect,
        NativeSelectOption,
    } from '@/components/ui/native-select';
    import { Textarea } from '@/components/ui/textarea';
    import VariantSwitcher from '@/components/VariantSwitcher.svelte';
    import { ARTICLE_VARIANTS } from '@/lib/articleEdit';
    import { useLayoutVariant } from '@/lib/useLayoutVariant.svelte';
    import type { ArticleEditFields, ArticleEditPreview } from '@/types';

    let {
        title = $bindable(),
        slug = $bindable(),
        category = $bindable(),
        teaser = $bindable(),
        content = $bindable(),
        publishedAt = $bindable(),
        categories,
        errors,
    }: {
        title: string;
        /** Leer = wird aus dem Titel erzeugt. */
        slug: string;
        category: string;
        teaser: string;
        content: string;
        /** Wert für datetime-local in Ortszeit, leer = Entwurf. */
        publishedAt: string;
        categories: Record<string, string>;
        errors: Partial<Record<string, string>>;
    } = $props();

    // Layout-Variante: Auswahl steht in der URL (?variant=B); ohne Parameter gilt A.
    const layoutVariant = useLayoutVariant(ARTICLE_VARIANTS);

    const preview: ArticleEditPreview = $derived({
        title,
        teaser,
        categoryLabel: categories[category] ?? '',
        publishedAt,
        content,
    });

    // Die Snippets werden erst beim Rendern ausgewertet, nicht beim Initialisieren.
    const fields: ArticleEditFields = $derived({
        title: titleField,
        slug: slugField,
        teaser: teaserField,
        content: contentField,
        category: categoryField,
        publishedAt: publishedAtField,
        cover: coverField,
        seo: seoFields,
        meta: metaFields,
    });
</script>

{#snippet titleField(large = false)}
    <div class="flex flex-col gap-2">
        <Label for="title" class={large ? 'sr-only' : ''}>Titel</Label>
        <Input
            id="title"
            bind:value={title}
            maxlength={255}
            placeholder={large ? 'Titel des Artikels' : ''}
            class={large ? 'h-14 text-2xl font-bold' : ''}
        />
        <InputError message={errors.title} />
    </div>
{/snippet}

{#snippet slugField()}
    <div class="flex flex-col gap-2">
        <Label for="slug">Adresse</Label>
        <div
            class="flex items-center overflow-hidden rounded-md border focus-within:ring-2 focus-within:ring-ring"
        >
            <span
                class="border-r bg-muted px-3 py-2 text-sm text-muted-foreground"
                >/magazin/</span
            >
            <input
                id="slug"
                bind:value={slug}
                maxlength={255}
                placeholder="wird aus dem Titel erzeugt"
                class="min-w-0 flex-1 bg-transparent px-3 py-2 text-sm outline-none"
            />
        </div>
        <p class="text-sm text-muted-foreground">
            Nach dem Veröffentlichen besser nicht mehr ändern, sonst laufen
            Links ins Leere.
        </p>
        <InputError message={errors.slug} />
    </div>
{/snippet}

{#snippet teaserField()}
    <div class="flex flex-col gap-2">
        <Label for="teaser">Teaser</Label>
        <Textarea
            id="teaser"
            bind:value={teaser}
            rows={3}
            maxlength={300}
            placeholder="Kurzbeschreibung für Übersicht und Suchmaschinen"
        />
        <InputError message={errors.teaser} />
    </div>
{/snippet}

{#snippet contentField()}
    <div class="flex flex-col gap-2">
        <Label for="content">Inhalt</Label>
        <Textarea
            id="content"
            bind:value={content}
            rows={18}
            class="min-h-96 font-mono text-sm"
        />
        <p class="text-sm text-muted-foreground">
            Markdown: <code>## Überschrift</code>, <code>- Liste</code>,
            <code>**fett**</code>,
            <code>[Linktext](https://…)</code>. Leerzeile = neuer Absatz.
        </p>
        <InputError message={errors.content} />
    </div>
{/snippet}

{#snippet categoryField()}
    <div class="flex flex-col gap-2">
        <Label for="category">Kategorie</Label>
        <NativeSelect id="category" bind:value={category} class="w-full">
            {#each Object.entries(categories) as [key, label] (key)}
                <NativeSelectOption value={key}>{label}</NativeSelectOption>
            {/each}
        </NativeSelect>
        <InputError message={errors.category} />
    </div>
{/snippet}

{#snippet publishedAtField()}
    <div class="flex flex-col gap-2">
        <Label for="published_at">Veröffentlichen am</Label>
        <Input
            id="published_at"
            type="datetime-local"
            bind:value={publishedAt}
        />
        <p class="text-sm text-muted-foreground">
            Leer = Entwurf. Ein Termin in der Zukunft plant den Artikel.
        </p>
        <InputError message={errors.published_at} />
    </div>
{/snippet}

<!-- Vorbereitete, noch nicht angebundene Felder: gesperrt, nie Teil des Formulars. -->
{#snippet coverField()}
    <DisabledField
        label="Titelbild"
        kind="file"
        hint="Erscheint in der Übersicht und beim Teilen."
    />
{/snippet}

{#snippet seoFields()}
    <DisabledField
        label="SEO-Titel"
        placeholder="Abweichender Titel für Suchmaschinen"
    />
    <DisabledField
        label="Meta-Beschreibung"
        kind="textarea"
        placeholder="Beschreibung für Suchergebnisse (ca. 155 Zeichen)"
    />
{/snippet}

{#snippet metaFields()}
    <DisabledField label="Autor" placeholder="Name der Autorin / des Autors" />
    <DisabledField label="Schlagwörter" placeholder="z. B. Rollator, Pflege" />
    <DisabledField
        label="Verwandte Produkte"
        kind="select"
        placeholder="Produkt auswählen"
        hint="Werden unter dem Artikel als Empfehlung angezeigt."
    />
{/snippet}

{#if layoutVariant.current === 'B'}
    <ArticleLayoutTabs {fields} {errors} />
{:else if layoutVariant.current === 'C'}
    <ArticleLayoutFocus {fields} {errors} />
{:else if layoutVariant.current === 'D'}
    <ArticleLayoutSections {fields} {errors} />
{:else if layoutVariant.current === 'E'}
    <ArticleLayoutPreview {fields} {errors} {preview} />
{:else}
    <ArticleLayoutSidebar {fields} {errors} />
{/if}

<VariantSwitcher
    options={ARTICLE_VARIANTS}
    current={layoutVariant.current}
    onselect={layoutVariant.select}
/>
