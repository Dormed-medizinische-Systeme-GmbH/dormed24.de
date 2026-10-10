<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'Social-Media', href: '/admin/social-posts' },
            { title: 'Neuer Beitrag' },
        ],
    };
</script>

<script lang="ts">
    import { setLayoutProps, useForm } from '@inertiajs/svelte';
    import * as SocialPostController from '@/actions/App/Http/Controllers/Admin/SocialPostController';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { fromDateTimeLocal } from '@/lib/date';
    import { guardUnsavedChanges } from '@/lib/unsavedChanges';
    import SocialPostFields from './SocialPostFields.svelte';

    let {
        platforms,
        maxContentLength,
    }: { platforms: Record<string, string>; maxContentLength: number } =
        $props();

    const form = useForm({
        title: '',
        content: '',
        platforms: [] as string[],
        scheduled_at: '',
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
        })).post(SocialPostController.store.url());
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

<AppHead title="Neuer Beitrag — Social-Media — Admin" />

<div class="flex h-full flex-1 flex-col gap-6 p-4 pb-20">
    <h1 class="sr-only">Neuer Social-Media-Beitrag</h1>

    <form onsubmit={submit} class="flex max-w-6xl flex-col gap-6">
        <SocialPostFields
            bind:title={form.title}
            bind:content={form.content}
            bind:platforms={form.platforms}
            bind:scheduledAt={form.scheduled_at}
            platformOptions={platforms}
            {maxContentLength}
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
