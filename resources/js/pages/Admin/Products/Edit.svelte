<script module lang="ts">
    // Breadcrumb mit dem Produktnamen statt „Bearbeiten“ (Layout-Callback über die Page-Props).
    export const layout = (props: { product: { name: string } }) => ({
        breadcrumbs: [
            { title: 'Produkte', href: '/admin/products' },
            { title: props.product.name },
        ],
    });
</script>

<script lang="ts">
    import { setLayoutProps, useForm } from '@inertiajs/svelte';
    import ExternalLink from '@lucide/svelte/icons/external-link';
    import * as AdminProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
    import * as ProductController from '@/actions/App/Http/Controllers/ProductController';
    import AppHead from '@/components/AppHead.svelte';
    import EditLayoutColumns from '@/components/product-edit/EditLayoutColumns.svelte';
    import EditLayoutGallery from '@/components/product-edit/EditLayoutGallery.svelte';
    import EditLayoutPreview from '@/components/product-edit/EditLayoutPreview.svelte';
    import EditLayoutSections from '@/components/product-edit/EditLayoutSections.svelte';
    import EditLayoutTabs from '@/components/product-edit/EditLayoutTabs.svelte';
    import RowActions from '@/components/RowActions.svelte';
    import SpecialtyPicker from '@/components/SpecialtyPicker.svelte';
    import TechnicalSpecsEditor from '@/components/TechnicalSpecsEditor.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import * as Select from '@/components/ui/select';
    import VariantSwitcher from '@/components/VariantSwitcher.svelte';
    import { EDIT_VARIANTS } from '@/lib/productEdit';
    import { guardUnsavedChanges } from '@/lib/unsavedChanges';
    import { useLayoutVariant } from '@/lib/useLayoutVariant.svelte';
    import type {
        Category,
        Manufacturer,
        ProductEditFields,
        ProductEditPreview,
        ProductEditProduct,
        ProductRating,
        Specialty,
    } from '@/types';

    let {
        product,
        manufacturers,
        categories,
        specialtyCategories,
        specialties,
        ratings = [],
    }: {
        product: ProductEditProduct;
        manufacturers: Manufacturer[];
        categories: Category[];
        specialtyCategories: string[];
        specialties: Specialty[];
        ratings?: ProductRating[];
    } = $props();

    // svelte-ignore state_referenced_locally
    const form = useForm({
        name: product.name,
        description: product.description ?? '',
        technical_specs: (product.technical_specs ?? []).map((spec) => ({
            ...spec,
        })),
        price: product.price,
        manufacturer_id: product.manufacturer_id
            ? String(product.manufacturer_id)
            : '',
        category_slug: product.category_slug ?? '',
        specialties: [...(product.specialties ?? [])],
        is_available: product.is_available,
    });

    const selectedManufacturer = $derived(
        manufacturers.find((m) => String(m.id) === form.manufacturer_id) ??
            null,
    );
    const selectedCategory = $derived(
        categories.find((c) => c.slug === form.category_slug) ?? null,
    );

    // Fachrichtungen nur für Kategorien mit has_specialties (config/categories.php).
    const hasSpecialties = $derived(
        specialtyCategories.includes(form.category_slug),
    );

    // Layout-Variante: Auswahl steht in der URL (?variant=B); ohne Parameter gilt A.
    const layoutVariant = useLayoutVariant(EDIT_VARIANTS);

    const preview: ProductEditPreview = $derived({
        name: form.name,
        description: form.description,
        price: form.price,
        isAvailable: form.is_available,
        manufacturer: selectedManufacturer,
        category: selectedCategory,
        specs: form.technical_specs,
    });

    function submit(e: SubmitEvent) {
        e.preventDefault();
        save();
    }

    function save() {
        if (!hasSpecialties) {
            form.specialties = [];
        }

        form.put(AdminProductController.update.url(product.id));
    }

    // Drei-Punkte-Menü (Anzeigen/Löschen) und Speichern rechts im Admin-Header.
    $effect(() => {
        setLayoutProps({ actions: headerActions });
    });

    // Rückfrage beim Verlassen, solange die Stammdaten ungespeichert sind.
    $effect(() => guardUnsavedChanges(() => form.isDirty));

    // Die Snippets werden erst beim Rendern ausgewertet, nicht beim Initialisieren.
    const fields: ProductEditFields = $derived({
        name: nameField,
        price: priceField,
        category: categoryField,
        specialties: specialtiesField,
        manufacturer: manufacturerField,
        description: descriptionField,
        specs: specsField,
        availability: availabilityField,
        actions: formActions,
    });
</script>

{#snippet headerActions()}
    <RowActions
        links={[
            {
                href: ProductController.show.url(product.id),
                label: 'Im Shop ansehen',
                icon: ExternalLink,
                external: true,
            },
        ]}
        destroyHref={AdminProductController.destroy.url(product.id)}
        confirmMessage={`Produkt „${product.name}“ wirklich löschen?`}
    />
    <Button onclick={save} disabled={form.processing}>
        {form.processing ? 'Speichert…' : 'Speichern'}
    </Button>
{/snippet}

{#snippet nameField()}
    <div class="flex flex-col gap-2">
        <Label for="name">Name</Label>
        <Input id="name" bind:value={form.name} placeholder="Produktname" />
        {#if form.errors.name}
            <p class="text-sm text-destructive">{form.errors.name}</p>
        {/if}
    </div>
{/snippet}

{#snippet priceField()}
    <div class="flex flex-col gap-2">
        <Label for="price">Preis (€)</Label>
        <Input
            id="price"
            type="number"
            step="0.01"
            min="0"
            bind:value={form.price}
            placeholder="0.00"
        />
        {#if form.errors.price}
            <p class="text-sm text-destructive">{form.errors.price}</p>
        {/if}
    </div>
{/snippet}

{#snippet categoryField()}
    <div class="flex flex-col gap-2">
        <Label>Kategorie</Label>
        <Select.Root type="single" bind:value={form.category_slug}>
            <Select.Trigger class="w-full">
                {selectedCategory?.name ?? 'Kategorie wählen'}
            </Select.Trigger>
            <Select.Content>
                {#each categories as c (c.slug)}
                    <Select.Item value={c.slug}>{c.name}</Select.Item>
                {/each}
            </Select.Content>
        </Select.Root>
        {#if form.errors.category_slug}
            <p class="text-sm text-destructive">
                {form.errors.category_slug}
            </p>
        {/if}
    </div>
{/snippet}

{#snippet specialtiesField()}
    {#if hasSpecialties}
        <SpecialtyPicker
            bind:selected={form.specialties}
            options={specialties}
            errors={form.errors}
        />
    {/if}
{/snippet}

{#snippet manufacturerField()}
    <div class="flex flex-col gap-2">
        <Label>Hersteller</Label>
        <Select.Root type="single" bind:value={form.manufacturer_id}>
            <Select.Trigger class="w-full">
                {selectedManufacturer?.name ?? 'Hersteller wählen'}
            </Select.Trigger>
            <Select.Content>
                {#each manufacturers as m (m.id)}
                    <Select.Item value={String(m.id)}>{m.name}</Select.Item>
                {/each}
            </Select.Content>
        </Select.Root>
        {#if form.errors.manufacturer_id}
            <p class="text-sm text-destructive">
                {form.errors.manufacturer_id}
            </p>
        {/if}
    </div>
{/snippet}

{#snippet descriptionField()}
    <div class="flex flex-col gap-2">
        <Label for="description">Beschreibung</Label>
        <textarea
            id="description"
            bind:value={form.description}
            rows={8}
            placeholder="Produktbeschreibung…"
            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
        ></textarea>
        {#if form.errors.description}
            <p class="text-sm text-destructive">{form.errors.description}</p>
        {/if}
    </div>
{/snippet}

{#snippet specsField(hideHeader = false)}
    <TechnicalSpecsEditor
        bind:specs={form.technical_specs}
        errors={form.errors}
        {hideHeader}
    />
{/snippet}

{#snippet availabilityField()}
    <div
        class="flex items-center justify-between rounded-lg border bg-card p-4"
    >
        <div>
            <p class="text-sm font-medium">Verfügbarkeit</p>
            <p class="text-xs text-muted-foreground">
                Produkt ist im Shop bestellbar
            </p>
        </div>
        <button
            type="button"
            role="switch"
            aria-label="Verfügbarkeit umschalten"
            aria-checked={form.is_available}
            onclick={() => (form.is_available = !form.is_available)}
            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring {form.is_available
                ? 'bg-primary'
                : 'bg-input'}"
        >
            <span
                class="pointer-events-none inline-block size-5 rounded-full bg-white shadow-lg ring-0 transition-transform {form.is_available
                    ? 'translate-x-5'
                    : 'translate-x-0'}"
            ></span>
        </button>
    </div>
{/snippet}

{#snippet formActions()}
    <div class="flex gap-3">
        <Button type="submit" disabled={form.processing}>
            {form.processing ? 'Speichert…' : 'Speichern'}
        </Button>
        <Button type="button" variant="outline" onclick={() => history.back()}>
            Abbrechen
        </Button>
    </div>
{/snippet}

<AppHead title="{product.name} bearbeiten — Admin" />

<div class="flex h-full flex-1 flex-col gap-6 p-4 pb-20">
    <h1 class="sr-only">{product.name} bearbeiten</h1>

    {#if layoutVariant.current === 'B'}
        <EditLayoutColumns {product} {ratings} {fields} onsubmit={submit} />
    {:else if layoutVariant.current === 'C'}
        <EditLayoutSections {product} {ratings} {fields} onsubmit={submit} />
    {:else if layoutVariant.current === 'D'}
        <EditLayoutGallery {product} {ratings} {fields} onsubmit={submit} />
    {:else if layoutVariant.current === 'E'}
        <EditLayoutPreview
            {product}
            {ratings}
            {fields}
            {preview}
            onsubmit={submit}
        />
    {:else}
        <EditLayoutTabs {product} {ratings} {fields} onsubmit={submit} />
    {/if}
</div>

<VariantSwitcher
    options={EDIT_VARIANTS}
    current={layoutVariant.current}
    onselect={layoutVariant.select}
/>
