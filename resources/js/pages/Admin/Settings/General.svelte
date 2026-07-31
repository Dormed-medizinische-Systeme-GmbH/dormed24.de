<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'Einstellungen', href: '/admin/settings' },
            { title: 'Allgemein', href: '/admin/settings/general' },
        ],
    };
</script>

<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import * as AdminSettingController from '@/actions/App/Http/Controllers/Admin/SettingController';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    let { settings }: { settings: Record<string, string> } = $props();

    // svelte-ignore state_referenced_locally
    const form = useForm({
        settings: {
            'shop.name': settings['shop.name'] ?? '',
            'shop.email': settings['shop.email'] ?? '',
            'shop.phone': settings['shop.phone'] ?? '',
            'shop.fax': settings['shop.fax'] ?? '',
            'shop.bank_account_holder':
                settings['shop.bank_account_holder'] ?? '',
            'shop.bank_iban': settings['shop.bank_iban'] ?? '',
            'shop.bank_bic': settings['shop.bank_bic'] ?? '',
            'shop.bank_name': settings['shop.bank_name'] ?? '',
        },
    });

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.put(AdminSettingController.update.url());
    }
</script>

<AppHead title="Allgemein - Einstellungen - Admin" />

<div class="max-w-2xl flex h-full flex-1 flex-col gap-6 p-4">
    <h1 class="text-xl font-semibold">Allgemein</h1>

    <form onsubmit={submit} class="flex flex-col gap-4">
        <div class="rounded-lg border bg-card p-5 flex flex-col gap-4">
            <div class="flex flex-col gap-1.5">
                <Label for="shop_name">Shop-Name</Label>
                <Input id="shop_name" bind:value={form.settings['shop.name']} />
            </div>
            <div class="flex flex-col gap-1.5">
                <Label for="shop_email">Kontakt-E-Mail</Label>
                <Input
                    id="shop_email"
                    type="email"
                    bind:value={form.settings['shop.email']}
                />
            </div>
            <div class="flex flex-col gap-1.5">
                <Label for="shop_phone">Telefon</Label>
                <Input
                    id="shop_phone"
                    type="tel"
                    bind:value={form.settings['shop.phone']}
                />
            </div>
            <div class="flex flex-col gap-1.5">
                <Label for="shop_fax">Fax</Label>
                <Input
                    id="shop_fax"
                    type="tel"
                    bind:value={form.settings['shop.fax']}
                />
            </div>
        </div>

        <div class="rounded-lg border bg-card p-5 flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <h2 class="text-base font-semibold">Bankverbindung</h2>
                <p class="text-sm text-muted-foreground">
                    Wird in den Rechnungsdaten fur Bestellbestatigungen
                    verwendet.
                </p>
            </div>
            <div class="flex flex-col gap-1.5">
                <Label for="shop_bank_account_holder">Kontoinhaber</Label>
                <Input
                    id="shop_bank_account_holder"
                    bind:value={form.settings['shop.bank_account_holder']}
                />
            </div>
            <div class="flex flex-col gap-1.5">
                <Label for="shop_bank_iban">IBAN</Label>
                <Input
                    id="shop_bank_iban"
                    bind:value={form.settings['shop.bank_iban']}
                />
            </div>
            <div class="flex flex-col gap-1.5">
                <Label for="shop_bank_bic">BIC</Label>
                <Input
                    id="shop_bank_bic"
                    bind:value={form.settings['shop.bank_bic']}
                />
            </div>
            <div class="flex flex-col gap-1.5">
                <Label for="shop_bank_name">Bankname</Label>
                <Input
                    id="shop_bank_name"
                    bind:value={form.settings['shop.bank_name']}
                />
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <Button type="submit" disabled={form.processing}>
                {form.processing ? 'Speichern...' : 'Speichern'}
            </Button>
        </div>
    </form>
</div>
