<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'Einstellungen', href: '/admin/settings' },
            { title: 'Zahlungsarten', href: '/admin/settings/payment' },
        ],
    };
</script>

<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import { Loader2 } from '@lucide/svelte';
    import { toast } from 'svelte-sonner';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { fetchJson } from '@/lib/http';
    import * as AdminSettingController from '@/actions/App/Http/Controllers/Admin/SettingController';
    import { check as payPalCheck } from '@/routes/admin/settings/paypal';

    let {
        settings,
        hasSensitive,
        paymentMode,
    }: {
        settings: Record<string, string>;
        hasSensitive: Record<string, boolean>;
        paymentMode: 'sandbox' | 'live';
    } = $props();

    // svelte-ignore state_referenced_locally
    const form = useForm({
        settings: {
            'paypal.sandbox.client_id':
                settings['paypal.sandbox.client_id'] ?? '',
            'paypal.sandbox.client_secret': hasSensitive[
                'paypal.sandbox.client_secret'
            ]
                ? '••••••••'
                : '',
            'paypal.sandbox.merchant_id':
                settings['paypal.sandbox.merchant_id'] ?? '',
            'paypal.live.client_id': settings['paypal.live.client_id'] ?? '',
            'paypal.live.client_secret': hasSensitive[
                'paypal.live.client_secret'
            ]
                ? '••••••••'
                : '',
            'paypal.live.app_id': settings['paypal.live.app_id'] ?? '',
            'paypal.live.merchant_id':
                settings['paypal.live.merchant_id'] ?? '',
            'paypal.webhook_id': hasSensitive['paypal.webhook_id']
                ? '••••••••'
                : '',
        },
    });

    const isSandbox = $derived(paymentMode === 'sandbox');

    let checkingPayPal = $state(false);

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.put(AdminSettingController.update.url());
    }

    async function checkPayPal() {
        checkingPayPal = true;

        try {
            const { ok, data } = await fetchJson<{ message?: string }>(
                payPalCheck.url(),
                { method: 'POST' },
            );

            if (ok) {
                toast.success(data.message ?? 'Verbindung erfolgreich.');
            } else {
                toast.error(data.message ?? 'PayPal: Verbindungsfehler');
            }
        } catch {
            toast.error('PayPal: Verbindungsfehler');
        } finally {
            checkingPayPal = false;
        }
    }
</script>

<AppHead title="Zahlungsarten — Einstellungen — Admin" />

<div class="flex h-full flex-1 flex-col gap-6 p-4 max-w-2xl">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Zahlungsarten</h1>
        <span
            class="rounded-full px-3 py-1 text-xs font-semibold {isSandbox
                ? 'bg-amber-100 text-amber-800'
                : 'bg-green-100 text-green-800'}"
        >
            {isSandbox ? 'Sandbox' : 'Live'}
        </span>
    </div>

    <form onsubmit={submit} class="flex flex-col gap-6">
        <p class="text-xs text-muted-foreground">
            Der Betriebsmodus (Sandbox/Live) ergibt sich ausschließlich aus der
            Umgebung (<code>APP_ENV</code>) und ist hier nicht umschaltbar.
            Beide Zugangsdaten-Sets bleiben unabhängig davon editierbar.
        </p>

        <!-- PayPal-Felder (Zahlarten sind fix: Rechnung + PayPal) -->
        <div class="rounded-lg border bg-card p-5 flex flex-col gap-4">
            <p class="text-sm font-semibold">PayPal</p>

            <div class="flex flex-col gap-3 rounded-md bg-muted/40 p-4">
                <p
                    class="text-xs font-medium text-muted-foreground uppercase tracking-wide"
                >
                    Sandbox-Zugangsdaten{isSandbox ? ' (aktiv)' : ''}
                </p>
                <div class="flex flex-col gap-1.5">
                    <Label for="pp_client_id">Client ID</Label>
                    <Input
                        id="pp_client_id"
                        bind:value={form.settings['paypal.sandbox.client_id']}
                    />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label for="pp_client_secret">
                        Client Secret
                        {#if hasSensitive['paypal.sandbox.client_secret']}
                            <span class="text-xs text-muted-foreground ml-1"
                                >(gesetzt — leer lassen zum Beibehalten)</span
                            >
                        {/if}
                    </Label>
                    <Input
                        id="pp_client_secret"
                        type="password"
                        placeholder={hasSensitive[
                            'paypal.sandbox.client_secret'
                        ]
                            ? '••••••••'
                            : ''}
                        bind:value={
                            form.settings['paypal.sandbox.client_secret']
                        }
                    />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label for="pp_merchant_id">Merchant ID</Label>
                    <Input
                        id="pp_merchant_id"
                        bind:value={form.settings['paypal.sandbox.merchant_id']}
                    />
                </div>
            </div>

            <div class="flex flex-col gap-3 rounded-md bg-muted/40 p-4">
                <p
                    class="text-xs font-medium text-muted-foreground uppercase tracking-wide"
                >
                    Live-Zugangsdaten{isSandbox ? '' : ' (aktiv)'}
                </p>
                <div class="flex flex-col gap-1.5">
                    <Label for="pp_live_client_id">Client ID</Label>
                    <Input
                        id="pp_live_client_id"
                        bind:value={form.settings['paypal.live.client_id']}
                    />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label for="pp_live_client_secret">
                        Client Secret
                        {#if hasSensitive['paypal.live.client_secret']}
                            <span class="text-xs text-muted-foreground ml-1"
                                >(gesetzt — leer lassen zum Beibehalten)</span
                            >
                        {/if}
                    </Label>
                    <Input
                        id="pp_live_client_secret"
                        type="password"
                        placeholder={hasSensitive['paypal.live.client_secret']
                            ? '••••••••'
                            : ''}
                        bind:value={form.settings['paypal.live.client_secret']}
                    />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label for="pp_live_app_id">App ID</Label>
                    <Input
                        id="pp_live_app_id"
                        placeholder="APP-…"
                        bind:value={form.settings['paypal.live.app_id']}
                    />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label for="pp_live_merchant_id">Merchant ID</Label>
                    <Input
                        id="pp_live_merchant_id"
                        bind:value={form.settings['paypal.live.merchant_id']}
                    />
                </div>
            </div>

            <!-- Webhook ID (shared) -->
            <div class="flex flex-col gap-1.5">
                <Label for="pp_webhook_id">
                    Webhook ID
                    {#if hasSensitive['paypal.webhook_id']}
                        <span class="text-xs text-muted-foreground ml-1"
                            >(gesetzt — leer lassen zum Beibehalten)</span
                        >
                    {/if}
                </Label>
                <Input
                    id="pp_webhook_id"
                    type="password"
                    placeholder={hasSensitive['paypal.webhook_id']
                        ? '••••••••'
                        : ''}
                    bind:value={form.settings['paypal.webhook_id']}
                />
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <Button
                type="button"
                variant="secondary"
                onclick={checkPayPal}
                disabled={checkingPayPal}
            >
                {#if checkingPayPal}<Loader2 class="size-4 animate-spin" />{/if}
                Verbindung prüfen
            </Button>
            <Button type="submit" disabled={form.processing}>
                {form.processing ? 'Speichern…' : 'Speichern'}
            </Button>
        </div>
    </form>
</div>
