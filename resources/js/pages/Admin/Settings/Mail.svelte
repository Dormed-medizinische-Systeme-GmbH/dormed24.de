<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'Einstellungen', href: '/admin/settings' },
            { title: 'Mailversand', href: '/admin/settings/mail' },
        ],
    };
</script>

<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import { Loader2 } from '@lucide/svelte';
    import { toast } from 'svelte-sonner';
    import * as AdminSettingController from '@/actions/App/Http/Controllers/Admin/SettingController';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { fetchJson } from '@/lib/http';
    import { check as mailCheck } from '@/routes/admin/settings/mail';

    let {
        settings,
        hasSensitive,
    }: {
        settings: Record<string, string>;
        hasSensitive: Record<string, boolean>;
    } = $props();

    // svelte-ignore state_referenced_locally
    const form = useForm({
        settings: {
            'mail.smtp_host': settings['mail.smtp_host'] ?? '',
            'mail.smtp_port': settings['mail.smtp_port'] ?? '',
            'mail.smtp_user': settings['mail.smtp_user'] ?? '',
            'mail.smtp_password': '',
        },
    });

    let checkingMail = $state(false);

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.put(AdminSettingController.update.url());
    }

    async function checkMail() {
        checkingMail = true;

        try {
            const { ok, data } = await fetchJson<{ message?: string }>(
                mailCheck.url(),
                { method: 'POST' },
            );

            if (ok) {
                toast.success(data.message ?? 'Testmail versendet.');
            } else {
                toast.error(data.message ?? 'Verbindungsfehler');
            }
        } catch {
            toast.error('Verbindungsfehler');
        } finally {
            checkingMail = false;
        }
    }
</script>

<AppHead title="Mailversand — Einstellungen — Admin" />

<div class="flex h-full flex-1 flex-col gap-6 p-4 max-w-2xl">
    <h1 class="text-xl font-semibold">Mailversand</h1>

    <form onsubmit={submit} class="flex flex-col gap-4">
        <div class="rounded-lg border bg-card p-5 flex flex-col gap-4">
            <div class="grid grid-cols-2 gap-4">
                <div class="flex flex-col gap-1.5">
                    <Label for="smtp_host">SMTP Host</Label>
                    <Input
                        id="smtp_host"
                        bind:value={form.settings['mail.smtp_host']}
                    />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label for="smtp_port">Port</Label>
                    <Input
                        id="smtp_port"
                        bind:value={form.settings['mail.smtp_port']}
                    />
                </div>
            </div>
            <div class="flex flex-col gap-1.5">
                <Label for="smtp_user">Benutzername</Label>
                <Input
                    id="smtp_user"
                    bind:value={form.settings['mail.smtp_user']}
                />
            </div>
            <div class="flex flex-col gap-1.5">
                <Label for="smtp_password">
                    Passwort
                    {#if hasSensitive['mail.smtp_password']}
                        <span class="text-xs text-muted-foreground ml-1"
                            >(gesetzt — leer lassen zum Beibehalten)</span
                        >
                    {/if}
                </Label>
                <Input
                    id="smtp_password"
                    type="password"
                    placeholder={hasSensitive['mail.smtp_password']
                        ? '••••••••'
                        : ''}
                    bind:value={form.settings['mail.smtp_password']}
                />
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <Button
                type="button"
                variant="secondary"
                onclick={checkMail}
                disabled={checkingMail}
            >
                {#if checkingMail}
                    <Loader2 class="size-4 animate-spin" />
                {/if}
                Verbindung prüfen
            </Button>
            <Button type="submit" disabled={form.processing}>
                {form.processing ? 'Speichern…' : 'Speichern'}
            </Button>
        </div>
    </form>
</div>
