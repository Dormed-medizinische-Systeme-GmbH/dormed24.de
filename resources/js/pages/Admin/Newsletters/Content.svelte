<script module lang="ts">
    import { edit } from '@/actions/App/Http/Controllers/Admin/NewsletterController';

    export const layout = (props: {
        newsletter: { id: number; name: string };
    }) => ({
        breadcrumbs: [
            { title: 'Newsletter', href: '/admin/newsletters' },
            {
                title: props.newsletter.name,
                href: edit.url(props.newsletter.id),
            },
            { title: 'Inhalt' },
        ],
    });
</script>

<script lang="ts">
    import { page, setLayoutProps } from '@inertiajs/svelte';
    import Save from '@lucide/svelte/icons/save';
    import Send from '@lucide/svelte/icons/send';
    import type { ProjectData } from 'grapesjs';
    import { onDestroy } from 'svelte';
    import { toast } from 'svelte-sonner';
    import * as NewsletterController from '@/actions/App/Http/Controllers/Admin/NewsletterController';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import { createAutosave } from '@/components/newsletter-editor/autosave';
    import type { AutosaveState } from '@/components/newsletter-editor/autosave';
    import NewsletterEditor from '@/components/newsletter-editor/NewsletterEditor.svelte';
    import type { EditorApi } from '@/components/newsletter-editor/NewsletterEditor.svelte';
    import { Button } from '@/components/ui/button';
    import { fetchJson, xsrfToken } from '@/lib/http';

    type Newsletter = {
        id: number;
        name: string;
        status: string;
        design_json: ProjectData | null;
        editable: boolean;
    };

    let {
        newsletter,
        testRecipient,
    }: { newsletter: Newsletter; testRecipient: string | null } = $props();

    let errors = $state<Record<string, string>>({});
    let compileWarnings = $state<string[]>([]);
    let saveState = $state<AutosaveState>('idle');
    let lastSaved = $state<string | null>(null);
    let sendingTest = $state(false);
    let editorApi: EditorApi | null = null;

    const shopUrl = $derived(
        (page.props.appUrl as string | undefined) ??
            (typeof window !== 'undefined' ? window.location.origin : ''),
    );

    async function save(): Promise<boolean> {
        if (!editorApi) {
            return false;
        }

        const content = editorApi.getContent();
        compileWarnings = content.errors;

        const { ok, status, data } = await fetchJson<{
            updated_at?: string;
            message?: string;
            errors?: Record<string, string[]>;
        }>(NewsletterController.update.url(newsletter.id), {
            method: 'PUT',
            body: {
                design_json: content.design_json,
                mjml: content.mjml,
                html: content.html,
            },
        });

        if (ok) {
            errors = {};
            lastSaved = data.updated_at ?? null;

            return true;
        }

        errors =
            status === 422 && data.errors
                ? Object.fromEntries(
                      Object.entries(data.errors).map(([field, messages]) => [
                          field,
                          messages[0],
                      ]),
                  )
                : { newsletter: data.message ?? 'Speichern fehlgeschlagen.' };

        return false;
    }

    const autosave = createAutosave(save, {
        onStateChange: (state) => (saveState = state),
    });

    onDestroy(() => autosave.cancel());

    // Speicherstatus, Testmail und Speichern rechts im Admin-Header, wie bei Produkten.
    $effect(() => {
        setLayoutProps({ actions: headerActions });
    });

    async function saveNow() {
        if (await autosave.flush()) {
            toast.success('Gespeichert.');
        } else {
            toast.error('Speichern fehlgeschlagen. Bitte die Hinweise prüfen.');
        }
    }

    async function sendTest() {
        sendingTest = true;

        try {
            if (!(await autosave.flush())) {
                toast.error(
                    'Vor der Testmail muss gespeichert werden. Bitte die Hinweise prüfen.',
                );

                return;
            }

            const { ok, data } = await fetchJson<{ message?: string }>(
                NewsletterController.sendTest.url(newsletter.id),
                { method: 'POST' },
            );

            if (ok) {
                toast.success(data.message ?? 'Testmail verschickt.');
            } else {
                toast.error(
                    data.message ??
                        'Die Testmail konnte nicht verschickt werden.',
                );
            }
        } finally {
            sendingTest = false;
        }
    }

    async function upload(files: File[]): Promise<string[]> {
        const form = new FormData();
        files.forEach((file) => form.append('files[]', file));

        const response = await fetch(NewsletterController.uploadAsset.url(), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: form,
        });
        const data = (await response.json().catch(() => ({}))) as {
            data?: { src: string }[];
            message?: string;
        };

        if (!response.ok || !data.data) {
            toast.error(
                data.message ??
                    'Bild-Upload fehlgeschlagen (jpg, png, webp oder gif, max. 2 MB).',
            );

            return [];
        }

        return data.data.map((asset) => asset.src);
    }

    function onKeydown(event: KeyboardEvent) {
        if ((event.ctrlKey || event.metaKey) && event.key === 's') {
            event.preventDefault();
            void saveNow();
        }
    }

    const saveStateLabel = $derived(
        {
            idle: '',
            dirty: 'Ungespeicherte Änderungen',
            saving: 'Wird gespeichert …',
            saved: lastSaved ? `Gespeichert um ${lastSaved}` : 'Gespeichert',
            error: 'Speichern fehlgeschlagen',
        }[saveState],
    );
</script>

{#snippet headerActions()}
    <span
        class="hidden text-sm text-muted-foreground sm:inline"
        aria-live="polite"
    >
        {saveStateLabel}
    </span>
    <Button
        variant="outline"
        onclick={sendTest}
        disabled={sendingTest || !testRecipient}
        title={testRecipient
            ? `Testmail an ${testRecipient}`
            : 'In den Einstellungen ist keine Shop-E-Mail hinterlegt.'}
    >
        <Send class="size-4" />
        {sendingTest ? 'Wird gesendet …' : 'Testmail an Admin'}
    </Button>
    <Button
        onclick={saveNow}
        disabled={!newsletter.editable || saveState === 'saving'}
    >
        <Save class="size-4" />
        {saveState === 'saving' ? 'Speichert…' : 'Speichern'}
    </Button>
{/snippet}

<svelte:window onkeydown={onKeydown} />

<AppHead title={`Inhalt: ${newsletter.name}`} />

<div class="flex h-[calc(100svh-5rem)] flex-col gap-3 p-4">
    <InputError
        message={errors.newsletter ?? errors.html ?? errors.design_json}
    />
    {#if compileWarnings.length > 0}
        <details
            class="rounded-md border border-amber-300 bg-amber-50 p-2 text-sm"
        >
            <summary class="cursor-pointer">
                MJML meldet {compileWarnings.length} Hinweis(e)
            </summary>
            <ul class="mt-1 list-disc pl-5">
                {#each compileWarnings as warning, index (index)}
                    <li>{warning}</li>
                {/each}
            </ul>
        </details>
    {/if}

    <div class="min-h-0 flex-1">
        <NewsletterEditor
            designJson={newsletter.design_json}
            uploadUrl={NewsletterController.uploadAsset.url()}
            {upload}
            {shopUrl}
            onReady={(api) => (editorApi = api)}
            onChange={() => autosave.touch()}
        />
    </div>
</div>
