<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Produkte', href: '/admin/products' }, { title: 'Neues Produkt' }],
    };
</script>

<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import * as AdminProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
    import AppHead from '@/components/AppHead.svelte';
    import SpecialtyPicker from '@/components/SpecialtyPicker.svelte';
    import TechnicalSpecsEditor from '@/components/TechnicalSpecsEditor.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import * as Select from '@/components/ui/select';
    import { guardUnsavedChanges } from '@/lib/unsavedChanges';

    type Manufacturer = { id: number; name: string };
    type Category = { slug: string; name: string };
    type Specialty = { slug: string; name: string };

    let {
        manufacturers,
        categories,
        specialtyCategories,
        specialties,
    }: {
        manufacturers: Manufacturer[];
        categories: Category[];
        specialtyCategories: string[];
        specialties: Specialty[];
    } = $props();

    const form = useForm({
        name: '',
        description: '',
        technical_specs: [] as { label: string; value: string }[],
        price: '',
        manufacturer_id: '',
        category_slug: '',
        specialties: [] as string[],
        is_available: true,
    });

    const selectedManufacturerLabel = $derived(
        manufacturers.find((m) => String(m.id) === form.manufacturer_id)?.name ?? 'Hersteller wählen',
    );

    const selectedCategoryLabel = $derived(
        categories.find((c) => c.slug === form.category_slug)?.name ?? 'Kategorie wählen',
    );

    // Rückfrage beim Verlassen, solange etwas eingegeben, aber nicht gespeichert ist.
    $effect(() => guardUnsavedChanges(() => form.isDirty));

    // Fachrichtungen nur für Kategorien mit has_specialties (config/categories.php).
    const hasSpecialties = $derived(specialtyCategories.includes(form.category_slug));

    function submit(e: SubmitEvent) {
        e.preventDefault();

        if (!hasSpecialties) {
            form.specialties = [];
        }

        form.post(AdminProductController.store.url());
    }
</script>

<AppHead title="Neues Produkt — Admin" />

<div class="flex h-full flex-1 flex-col gap-6 p-4">
    <h1 class="text-xl font-semibold">Neues Produkt</h1>

    <form onsubmit={submit} class="flex max-w-2xl flex-col gap-6">
        <div class="flex flex-col gap-2">
            <Label for="name">Name</Label>
            <Input id="name" bind:value={form.name} placeholder="Produktname" />
            {#if form.errors.name}
                <p class="text-sm text-destructive">{form.errors.name}</p>
            {/if}
        </div>

        <div class="flex flex-col gap-2">
            <Label for="description">Beschreibung</Label>
            <textarea
                id="description"
                bind:value={form.description}
                rows={5}
                placeholder="Produktbeschreibung…"
                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
            ></textarea>
            {#if form.errors.description}
                <p class="text-sm text-destructive">{form.errors.description}</p>
            {/if}
        </div>

        <TechnicalSpecsEditor bind:specs={form.technical_specs} errors={form.errors} />

        <div class="flex flex-col gap-2">
            <Label for="price">Preis (€)</Label>
            <Input id="price" type="number" step="0.01" min="0" bind:value={form.price} placeholder="0.00" />
            {#if form.errors.price}
                <p class="text-sm text-destructive">{form.errors.price}</p>
            {/if}
        </div>

        <div class="flex flex-col gap-2">
            <Label>Hersteller</Label>
            <Select.Root
                type="single"
                value={form.manufacturer_id}
                onValueChange={(v) => (form.manufacturer_id = v ?? '')}
            >
                <Select.Trigger class="w-full">
                    {selectedManufacturerLabel}
                </Select.Trigger>
                <Select.Content>
                    {#each manufacturers as m (m.id)}
                        <Select.Item value={String(m.id)}>{m.name}</Select.Item>
                    {/each}
                </Select.Content>
            </Select.Root>
            {#if form.errors.manufacturer_id}
                <p class="text-sm text-destructive">{form.errors.manufacturer_id}</p>
            {/if}
        </div>

        <div class="flex flex-col gap-2">
            <Label>Kategorie</Label>
            <Select.Root
                type="single"
                value={form.category_slug}
                onValueChange={(v) => (form.category_slug = v ?? '')}
            >
                <Select.Trigger class="w-full">
                    {selectedCategoryLabel}
                </Select.Trigger>
                <Select.Content>
                    {#each categories as c (c.slug)}
                        <Select.Item value={c.slug}>{c.name}</Select.Item>
                    {/each}
                </Select.Content>
            </Select.Root>
            {#if form.errors.category_slug}
                <p class="text-sm text-destructive">{form.errors.category_slug}</p>
            {/if}
        </div>

        {#if hasSpecialties}
            <SpecialtyPicker bind:selected={form.specialties} options={specialties} errors={form.errors} />
        {/if}

        <div class="flex items-center justify-between rounded-lg border bg-card p-4">
            <div>
                <p class="text-sm font-medium">Verfügbarkeit</p>
                <p class="text-xs text-muted-foreground">Produkt ist im Shop bestellbar</p>
            </div>
            <button
                type="button"
                role="switch"
                aria-label="Verfügbarkeit umschalten"
                aria-checked={form.is_available}
                onclick={() => (form.is_available = !form.is_available)}
                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring {form.is_available ? 'bg-primary' : 'bg-input'}"
            >
                <span class="pointer-events-none inline-block size-5 rounded-full bg-white shadow-lg ring-0 transition-transform {form.is_available ? 'translate-x-5' : 'translate-x-0'}"></span>
            </button>
        </div>

        <div class="flex gap-3">
            <Button type="submit" disabled={form.processing}>
                {form.processing ? 'Erstellt…' : 'Erstellen'}
            </Button>
            <Button type="button" variant="outline" onclick={() => history.back()}>
                Abbrechen
            </Button>
        </div>
    </form>
</div>
