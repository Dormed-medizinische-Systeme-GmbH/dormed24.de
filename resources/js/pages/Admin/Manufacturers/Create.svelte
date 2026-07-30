<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'Hersteller', href: '/admin/manufacturers' },
            { title: 'Neuer Hersteller' },
        ],
    };
</script>

<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import * as AdminManufacturerController from '@/actions/App/Http/Controllers/Admin/ManufacturerController';

    const form = useForm({
        name: '',
        country: '',
        contact_email: '',
    });

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.post(AdminManufacturerController.store.url());
    }
</script>

<AppHead title="Neuer Hersteller — Admin" />

<div class="flex h-full flex-1 flex-col gap-6 p-4">
    <h1 class="text-xl font-semibold">Neuer Hersteller</h1>

    <form onsubmit={submit} class="flex max-w-2xl flex-col gap-6">
        <div class="flex flex-col gap-2">
            <Label for="name">Name</Label>
            <Input
                id="name"
                bind:value={form.name}
                placeholder="Herstellername"
            />
            {#if form.errors.name}
                <p class="text-sm text-destructive">{form.errors.name}</p>
            {/if}
        </div>

        <div class="flex flex-col gap-2">
            <Label for="country">Land</Label>
            <Input
                id="country"
                bind:value={form.country}
                placeholder="Deutschland"
            />
            {#if form.errors.country}
                <p class="text-sm text-destructive">{form.errors.country}</p>
            {/if}
        </div>

        <div class="flex flex-col gap-2">
            <Label for="contact_email">Kontakt-E-Mail</Label>
            <Input
                id="contact_email"
                type="email"
                bind:value={form.contact_email}
                placeholder="kontakt@hersteller.de"
            />
            {#if form.errors.contact_email}
                <p class="text-sm text-destructive">
                    {form.errors.contact_email}
                </p>
            {/if}
        </div>

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
