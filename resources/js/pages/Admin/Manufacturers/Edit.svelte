<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Hersteller', href: '/admin/manufacturers' }, { title: 'Bearbeiten' }],
    };
</script>

<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import * as AdminManufacturerController from '@/actions/App/Http/Controllers/Admin/ManufacturerController';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { guardUnsavedChanges } from '@/lib/unsavedChanges';

    type Manufacturer = {
        id: number;
        name: string;
        country: string | null;
        contact_email: string | null;
    };

    let { manufacturer }: { manufacturer: Manufacturer } = $props();

    // svelte-ignore state_referenced_locally
    const form = useForm({
        name: manufacturer.name,
        country: manufacturer.country ?? '',
        contact_email: manufacturer.contact_email ?? '',
    });

    // Rückfrage beim Verlassen, solange Änderungen nicht gespeichert sind.
    $effect(() => guardUnsavedChanges(() => form.isDirty));

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.put(AdminManufacturerController.update.url(manufacturer.id));
    }
</script>

<AppHead title="{manufacturer.name} bearbeiten — Admin" />

<div class="flex h-full flex-1 flex-col gap-6 p-4">
    <h1 class="text-xl font-semibold">Hersteller bearbeiten</h1>

    <form onsubmit={submit} class="flex max-w-2xl flex-col gap-6">
        <div class="flex flex-col gap-2">
            <Label for="name">Name</Label>
            <Input id="name" bind:value={form.name} placeholder="Herstellername" />
            {#if form.errors.name}
                <p class="text-sm text-destructive">{form.errors.name}</p>
            {/if}
        </div>

        <div class="flex flex-col gap-2">
            <Label for="country">Land</Label>
            <Input id="country" bind:value={form.country} placeholder="Deutschland" />
            {#if form.errors.country}
                <p class="text-sm text-destructive">{form.errors.country}</p>
            {/if}
        </div>

        <div class="flex flex-col gap-2">
            <Label for="contact_email">Kontakt-E-Mail</Label>
            <Input id="contact_email" type="email" bind:value={form.contact_email} placeholder="kontakt@hersteller.de" />
            {#if form.errors.contact_email}
                <p class="text-sm text-destructive">{form.errors.contact_email}</p>
            {/if}
        </div>

        <div class="flex gap-3">
            <Button type="submit" disabled={form.processing}>
                {form.processing ? 'Speichert…' : 'Speichern'}
            </Button>
            <Button type="button" variant="outline" onclick={() => history.back()}>
                Abbrechen
            </Button>
        </div>
    </form>
</div>
