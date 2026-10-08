<script module lang="ts">
    export const layout = (props: { newsletter: { name: string } }) => ({
        breadcrumbs: [
            { title: 'Newsletter', href: '/admin/newsletters' },
            { title: props.newsletter.name },
        ],
    });
</script>

<script lang="ts">
    import { Link, setLayoutProps, useForm } from '@inertiajs/svelte';
    import LayoutTemplate from '@lucide/svelte/icons/layout-template';
    import Save from '@lucide/svelte/icons/save';
    import Send from '@lucide/svelte/icons/send';
    import { toast } from 'svelte-sonner';
    import * as NewsletterController from '@/actions/App/Http/Controllers/Admin/NewsletterController';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button, buttonVariants } from '@/components/ui/button';
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { fetchJson } from '@/lib/http';
    import { guardUnsavedChanges } from '@/lib/unsavedChanges';
    import NewsletterActions from './NewsletterActions.svelte';
    import StatusBadge from './StatusBadge.svelte';

    type Newsletter = {
        id: number;
        name: string;
        subject: string;
        preheader: string | null;
        status: string;
        html: string | null;
        editable: boolean;
        updated_at: string;
        sent_at: string | null;
    };

    let {
        newsletter,
        testRecipient,
    }: { newsletter: Newsletter; testRecipient: string | null } = $props();

    // svelte-ignore state_referenced_locally
    const form = useForm({
        name: newsletter.name,
        subject: newsletter.subject,
        preheader: newsletter.preheader ?? '',
    });

    let sendingTest = $state(false);

    // Rückfrage beim Verlassen, solange Änderungen nicht gespeichert sind.
    $effect(() => guardUnsavedChanges(() => form.isDirty));

    // Testmail, Inhalt und Speichern rechts im Admin-Header, wie bei Produkten.
    $effect(() => {
        setLayoutProps({ actions: headerActions });
    });

    function submit(event: SubmitEvent) {
        event.preventDefault();
        save();
    }

    function save() {
        form.transform((data) => ({
            ...data,
            preheader: data.preheader || null,
        })).put(NewsletterController.update.url(newsletter.id), {
            preserveScroll: true,
        });
    }

    async function sendTest() {
        sendingTest = true;

        try {
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

    const testMailHint = $derived(
        !testRecipient
            ? 'In den Einstellungen ist keine Shop-E-Mail hinterlegt.'
            : form.isDirty
              ? 'Bitte zuerst speichern.'
              : `Testmail an ${testRecipient}`,
    );
</script>

{#snippet headerActions()}
    <NewsletterActions {newsletter} showEdit={false} />
    <Button
        variant="outline"
        onclick={sendTest}
        disabled={sendingTest || !testRecipient || form.isDirty}
        title={testMailHint}
    >
        <Send class="size-4" />
        {sendingTest ? 'Wird gesendet …' : 'Testmail an Admin'}
    </Button>
    <Button onclick={save} disabled={!newsletter.editable || form.processing}>
        <Save class="size-4" />
        {form.processing ? 'Speichert…' : 'Speichern'}
    </Button>
{/snippet}

<AppHead title={`Newsletter: ${newsletter.name}`} />

<div class="grid flex-1 gap-6 p-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,40rem)]">
    <h1 class="sr-only">Newsletter {newsletter.name}</h1>

    <div class="flex flex-col gap-6">
        <Card>
            <CardHeader>
                <CardTitle>Allgemein</CardTitle>
                <CardDescription>
                    Betreff und Vorschautext sehen Empfänger im Postfach, den
                    internen Namen nur der Admin.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onsubmit={submit} class="flex flex-col gap-5">
                    <fieldset class="contents" disabled={!newsletter.editable}>
                        <div class="flex flex-col gap-2">
                            <Label for="newsletter-name">Interner Name</Label>
                            <Input
                                id="newsletter-name"
                                bind:value={form.name}
                                maxlength={255}
                            />
                            <InputError message={form.errors.name} />
                        </div>
                        <div class="flex flex-col gap-2">
                            <Label for="newsletter-subject">Betreff</Label>
                            <Input
                                id="newsletter-subject"
                                bind:value={form.subject}
                                maxlength={150}
                            />
                            <InputError message={form.errors.subject} />
                        </div>
                        <div class="flex flex-col gap-2">
                            <Label for="newsletter-preheader">
                                Vorschautext
                            </Label>
                            <Input
                                id="newsletter-preheader"
                                bind:value={form.preheader}
                                maxlength={200}
                                placeholder="Erscheint im Postfach neben dem Betreff"
                            />
                            <InputError message={form.errors.preheader} />
                        </div>
                    </fieldset>
                    <InputError
                        message={(form.errors as Record<string, string>)
                            .newsletter}
                    />
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Versand</CardTitle>
                <CardDescription>
                    Der Versand an Abonnenten kommt mit Phase 2. Bis dahin geht
                    nur die Testmail an die Shop-Adresse.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                    <dt class="text-muted-foreground">Status</dt>
                    <dd><StatusBadge status={newsletter.status} /></dd>
                    <dt class="text-muted-foreground">Zuletzt geändert</dt>
                    <dd>{newsletter.updated_at}</dd>
                    <dt class="text-muted-foreground">Verschickt</dt>
                    <dd>{newsletter.sent_at ?? '–'}</dd>
                    <dt class="text-muted-foreground">Testempfänger</dt>
                    <dd>{testRecipient ?? 'keine Shop-E-Mail hinterlegt'}</dd>
                </dl>
            </CardContent>
        </Card>
    </div>

    <Card class="min-h-[32rem]">
        <CardHeader
            class="flex flex-row items-start justify-between gap-4 space-y-0"
        >
            <div class="flex flex-col gap-1.5">
                <CardTitle>Inhalt</CardTitle>
                <CardDescription>So sieht die Mail aktuell aus.</CardDescription
                >
            </div>
            <Link
                href={NewsletterController.content.url(newsletter.id)}
                class={buttonVariants({ variant: 'outline' })}
            >
                <LayoutTemplate class="size-4" />
                {newsletter.editable ? 'Inhalt bearbeiten' : 'Inhalt ansehen'}
            </Link>
        </CardHeader>
        <CardContent class="flex flex-1 flex-col">
            {#if newsletter.html}
                <!-- Bereinigtes HTML, ohne Skripte und ohne Zugriff auf die Seite. -->
                <iframe
                    title="Vorschau des Newsletters"
                    srcdoc={newsletter.html}
                    sandbox=""
                    class="min-h-[36rem] w-full flex-1 rounded-md border bg-white"
                ></iframe>
            {:else}
                <div
                    class="flex flex-1 flex-col items-center justify-center gap-3 rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    <LayoutTemplate class="size-6" />
                    Noch kein Inhalt gespeichert.
                    <Link
                        href={NewsletterController.content.url(newsletter.id)}
                        class="font-medium text-[#1a6bbf] hover:underline"
                    >
                        Inhalt gestalten
                    </Link>
                </div>
            {/if}
        </CardContent>
    </Card>
</div>
