<script module lang="ts">
    // Breadcrumb mit dem Beitragstitel statt „Bearbeiten“.
    export const layout = (props: { post: { title: string } }) => ({
        breadcrumbs: [
            { title: 'Social-Media', href: '/admin/social-posts' },
            { title: props.post.title },
        ],
    });
</script>

<script lang="ts">
    import { setLayoutProps, useForm } from '@inertiajs/svelte';
    import * as SocialPostController from '@/actions/App/Http/Controllers/Admin/SocialPostController';
    import AppHead from '@/components/AppHead.svelte';
    import RowActions from '@/components/RowActions.svelte';
    import StatusBadge from '@/components/StatusBadge.svelte';
    import { Button } from '@/components/ui/button';
    import { fromDateTimeLocal, toDateTimeLocal } from '@/lib/date';
    import type { StatusMap } from '@/lib/status';
    import { guardUnsavedChanges } from '@/lib/unsavedChanges';
    import SocialPostFields from './SocialPostFields.svelte';

    type Post = {
        id: number;
        title: string;
        content: string;
        platforms: string[];
        status: string;
        scheduled_at: string | null;
        editable: boolean;
    };

    let {
        post,
        platforms,
        maxContentLength,
        statuses,
    }: {
        post: Post;
        platforms: Record<string, string>;
        maxContentLength: number;
        statuses: StatusMap;
    } = $props();

    // svelte-ignore state_referenced_locally
    const form = useForm({
        title: post.title,
        content: post.content,
        platforms: [...post.platforms],
        scheduled_at: toDateTimeLocal(post.scheduled_at),
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
            scheduled_at: fromDateTimeLocal(data.scheduled_at),
        })).put(SocialPostController.update.url(post.id));
    }

    // Zweiter Speichern-Button rechts im Admin-Header.
    $effect(() => {
        setLayoutProps({ actions: headerActions });
    });
</script>

{#snippet headerActions()}
    <StatusBadge status={post.status} {statuses} />
    <RowActions
        destroyHref={SocialPostController.destroy.url(post.id)}
        confirmMessage={`Beitrag „${post.title}“ wirklich löschen?`}
    />
    <Button onclick={save} disabled={!post.editable || form.processing}>
        {form.processing ? 'Speichert…' : 'Speichern'}
    </Button>
{/snippet}

<AppHead title="{post.title} — Social-Media — Admin" />

<div class="flex h-full flex-1 flex-col gap-6 p-4 pb-20">
    <h1 class="sr-only">{post.title} bearbeiten</h1>

    {#if !post.editable}
        <p
            class="max-w-2xl rounded-md border border-amber-300 bg-amber-50 p-3 text-sm"
        >
            Dieser Beitrag ist veröffentlicht und kann nicht mehr geändert
            werden.
        </p>
    {/if}

    <form onsubmit={submit} class="flex max-w-6xl flex-col gap-6">
        <SocialPostFields
            bind:title={form.title}
            bind:content={form.content}
            bind:platforms={form.platforms}
            bind:scheduledAt={form.scheduled_at}
            platformOptions={platforms}
            {maxContentLength}
            errors={form.errors}
            disabled={!post.editable}
        />

        <div class="flex gap-3">
            <Button type="submit" disabled={!post.editable || form.processing}>
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
