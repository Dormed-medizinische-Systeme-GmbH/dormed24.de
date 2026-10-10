<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'Magazin', href: '/admin/articles' },
            { title: 'Neuer Artikel' },
        ],
    };
</script>

<script lang="ts">
    import { setLayoutProps, useForm } from '@inertiajs/svelte';
    import * as ArticleController from '@/actions/App/Http/Controllers/Admin/ArticleController';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { fromDateTimeLocal } from '@/lib/date';
    import { guardUnsavedChanges } from '@/lib/unsavedChanges';
    import ArticleFields from './ArticleFields.svelte';

    let { categories }: { categories: Record<string, string> } = $props();

    // svelte-ignore state_referenced_locally
    const form = useForm({
        title: '',
        slug: '',
        category: Object.keys(categories)[0] ?? '',
        teaser: '',
        content: '',
        published_at: '',
    });

    // Rückfrage beim Verlassen, solange Änderungen nicht gespeichert sind.
    $effect(() => guardUnsavedChanges(() => form.isDirty));

    function submit(event: SubmitEvent) {
        event.preventDefault();
        save();
    }

    function save() {
        form.transform((data) => ({
            ...data,
            published_at: fromDateTimeLocal(data.published_at),
        })).post(ArticleController.store.url());
    }

    // Zweiter Speichern-Button rechts im Admin-Header.
    $effect(() => {
        setLayoutProps({ actions: headerActions });
    });
</script>

{#snippet headerActions()}
    <Button onclick={save} disabled={form.processing}>
        {form.processing ? 'Erstellt…' : 'Erstellen'}
    </Button>
{/snippet}

<AppHead title="Neuer Artikel — Magazin — Admin" />

<div class="flex h-full flex-1 flex-col gap-6 p-4 pb-20">
    <h1 class="sr-only">Neuer Magazin-Artikel</h1>

    <form onsubmit={submit} class="flex max-w-6xl flex-col gap-6">
        <ArticleFields
            bind:title={form.title}
            bind:slug={form.slug}
            bind:category={form.category}
            bind:teaser={form.teaser}
            bind:content={form.content}
            bind:publishedAt={form.published_at}
            {categories}
            errors={form.errors}
        />

        <div class="flex gap-3">
            <Button type="submit" disabled={form.processing}>
                {form.processing ? 'Erstellt…' : 'Erstellen'}
            </Button>
            <Button
                type="button"
                variant="outline"
                onclick={() => history.back()}
            >
                Abbrechen
            </Button>
        </div>
    </form>
</div>
