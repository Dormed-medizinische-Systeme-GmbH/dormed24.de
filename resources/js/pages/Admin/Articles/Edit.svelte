<script module lang="ts">
    // Breadcrumb mit dem Artikeltitel statt „Bearbeiten“.
    export const layout = (props: { article: { title: string } }) => ({
        breadcrumbs: [
            { title: 'Magazin', href: '/admin/articles' },
            { title: props.article.title },
        ],
    });
</script>

<script lang="ts">
    import { setLayoutProps, useForm } from '@inertiajs/svelte';
    import ExternalLink from '@lucide/svelte/icons/external-link';
    import * as ArticleController from '@/actions/App/Http/Controllers/Admin/ArticleController';
    import { show as magazineShow } from '@/actions/App/Http/Controllers/MagazineController';
    import AppHead from '@/components/AppHead.svelte';
    import { Button, buttonVariants } from '@/components/ui/button';
    import { fromDateTimeLocal, toDateTimeLocal } from '@/lib/date';
    import { guardUnsavedChanges } from '@/lib/unsavedChanges';
    import ArticleActions from './ArticleActions.svelte';
    import ArticleFields from './ArticleFields.svelte';
    import StatusBadge from './StatusBadge.svelte';

    type Article = {
        id: number;
        title: string;
        slug: string;
        category: string;
        teaser: string | null;
        content: string;
        status: string;
        published_at: string | null;
    };

    let {
        article,
        categories,
    }: { article: Article; categories: Record<string, string> } = $props();

    // svelte-ignore state_referenced_locally
    const form = useForm({
        title: article.title,
        slug: article.slug,
        category: article.category,
        teaser: article.teaser ?? '',
        content: article.content,
        published_at: toDateTimeLocal(article.published_at),
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
        })).put(ArticleController.update.url(article.id), {
            preserveScroll: true,
        });
    }

    // Status, Shop-Link, Aktionen und Speichern rechts im Admin-Header.
    $effect(() => {
        setLayoutProps({ actions: headerActions });
    });
</script>

{#snippet headerActions()}
    <StatusBadge status={article.status} />
    {#if article.status === 'published'}
        <a
            href={magazineShow.url(article.slug)}
            target="_blank"
            rel="noopener"
            class={buttonVariants({ variant: 'outline' })}
        >
            <ExternalLink class="size-4" />
            Im Shop ansehen
        </a>
    {/if}
    <ArticleActions {article} showEdit={false} />
    <Button onclick={save} disabled={form.processing}>
        {form.processing ? 'Speichert…' : 'Speichern'}
    </Button>
{/snippet}

<AppHead title="{article.title} — Magazin — Admin" />

<div class="flex h-full flex-1 flex-col gap-6 p-4">
    <h1 class="sr-only">{article.title} bearbeiten</h1>

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
                {form.processing ? 'Speichert…' : 'Speichern'}
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
