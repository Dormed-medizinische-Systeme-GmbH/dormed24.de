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
    import { useForm, router, setLayoutProps } from '@inertiajs/svelte';
    import {
        GripVertical,
        Trash2,
        ImagePlus,
        Star,
        Plus,
        Check,
    } from '@lucide/svelte';
    import * as AdminProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
    import * as AdminProductVariantController from '@/actions/App/Http/Controllers/Admin/ProductVariantController';
    import * as AdminRatingController from '@/actions/App/Http/Controllers/Admin/RatingController';
    import AppHead from '@/components/AppHead.svelte';
    import SpecialtyPicker from '@/components/SpecialtyPicker.svelte';
    import TechnicalSpecsEditor from '@/components/TechnicalSpecsEditor.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import * as Select from '@/components/ui/select';
    import * as Tabs from '@/components/ui/tabs';
    import { guardUnsavedChanges } from '@/lib/unsavedChanges';
    import { cn } from '@/lib/utils';
    import ProductActions from './ProductActions.svelte';

    type ProductImage = {
        id: number;
        path: string;
        sort_order: number;
        url: string;
    };

    type ProductVariant = {
        id: number;
        label: string;
        price: string;
        sort_order: number;
        is_default: boolean;
    };

    type Product = {
        id: number;
        name: string;
        description: string | null;
        technical_specs: { label: string; value: string }[] | null;
        price: string;
        manufacturer_id: number | null;
        category_slug: string | null;
        specialties: string[] | null;
        is_available: boolean;
        images: ProductImage[];
        variants: ProductVariant[];
    };

    type Rating = {
        id: number;
        stars: number;
        content: string;
        created_at: string | null;
        verified: boolean;
    };

    type Manufacturer = { id: number; name: string };
    type Category = { slug: string; name: string };
    type Specialty = { slug: string; name: string };

    let {
        product,
        manufacturers,
        categories,
        specialtyCategories,
        specialties,
        ratings = [],
    }: {
        product: Product;
        manufacturers: Manufacturer[];
        categories: Category[];
        specialtyCategories: string[];
        specialties: Specialty[];
        ratings?: Rating[];
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

    const selectedManufacturerLabel = $derived(
        manufacturers.find((m) => String(m.id) === form.manufacturer_id)
            ?.name ?? 'Hersteller wählen',
    );

    const selectedCategoryLabel = $derived(
        categories.find((c) => c.slug === form.category_slug)?.name ??
            'Kategorie wählen',
    );

    // Fachrichtungen nur für Kategorien mit has_specialties (config/categories.php).
    const hasSpecialties = $derived(
        specialtyCategories.includes(form.category_slug),
    );

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

    // Bild- und Variationen-Aktionen laden nur die Props neu: Ungespeicherte
    // Stammdaten und der aktive Reiter bleiben erhalten.
    const keepPageState = {
        preserveState: true,
        preserveScroll: true,
    } as const;

    // --- Image management ---

    // Lokale, editierbare Kopie der Bilder: re-derived, sobald sich die
    // product.images-Prop aendert (Upload/Loeschen), lokal per Drag-Reorder
    // ueberschreibbar (writable $derived).
    let images = $derived([...product.images]);
    let uploading = $state(false);
    let draggedId = $state<number | null>(null);
    let dragOverId = $state<number | null>(null);

    function uploadImage(e: Event) {
        const input = e.target as HTMLInputElement;
        const file = input.files?.[0];

        if (!file) {
            return;
        }

        uploading = true;
        const data = new FormData();
        data.append('image', file);

        router.post(`/admin/products/${product.id}/images`, data, {
            ...keepPageState,
            onFinish: () => {
                uploading = false;
                input.value = '';
            },
        });
    }

    function deleteRating(ratingId: number) {
        if (
            !confirm(
                'Bewertung wirklich löschen? Sie verschwindet sofort von der Produktseite.',
            )
        ) {
            return;
        }

        router.delete(
            AdminRatingController.destroy.url(ratingId),
            keepPageState,
        );
    }

    function deleteImage(imageId: number) {
        if (!confirm('Bild wirklich löschen?')) {
            return;
        }

        router.delete(
            `/admin/products/${product.id}/images/${imageId}`,
            keepPageState,
        );
    }

    function onDragStart(id: number) {
        draggedId = id;
    }

    function onDragOver(e: DragEvent, id: number) {
        e.preventDefault();
        dragOverId = id;
    }

    function onDrop(e: DragEvent, targetId: number) {
        e.preventDefault();

        if (draggedId === null || draggedId === targetId) {
            return;
        }

        const from = images.findIndex((img) => img.id === draggedId);
        const to = images.findIndex((img) => img.id === targetId);

        if (from === -1 || to === -1) {
            return;
        }

        const reordered = [...images];
        const [moved] = reordered.splice(from, 1);
        reordered.splice(to, 0, moved);
        images = reordered;

        router.patch(
            `/admin/products/${product.id}/images/reorder`,
            { ids: reordered.map((img) => img.id) },
            keepPageState,
        );

        draggedId = null;
        dragOverId = null;
    }

    function onDragEnd() {
        draggedId = null;
        dragOverId = null;
    }

    // --- Variant management ---

    const variants = $derived([...product.variants]);
    let newVariant = $state({ label: '', price: '', is_default: false });
    let editingVariantId = $state<number | null>(null);
    let editVariant = $state({ label: '', price: '', is_default: false });

    function addVariant() {
        if (!newVariant.label || !newVariant.price) {
            return;
        }

        router.post(
            AdminProductVariantController.store.url(product.id),
            {
                label: newVariant.label,
                price: newVariant.price,
                is_default: newVariant.is_default,
            },
            {
                ...keepPageState,
                onSuccess: () => {
                    newVariant = { label: '', price: '', is_default: false };
                },
            },
        );
    }

    function startEditVariant(variant: ProductVariant) {
        editingVariantId = variant.id;
        editVariant = {
            label: variant.label,
            price: variant.price,
            is_default: variant.is_default,
        };
    }

    function saveVariant(variantId: number) {
        router.put(
            AdminProductVariantController.update.url({
                product: product.id,
                variant: variantId,
            }),
            {
                label: editVariant.label,
                price: editVariant.price,
                is_default: editVariant.is_default,
            },
            {
                ...keepPageState,
                onSuccess: () => {
                    editingVariantId = null;
                },
            },
        );
    }

    function deleteVariant(variantId: number) {
        if (!confirm('Variation wirklich löschen?')) {
            return;
        }

        router.delete(
            AdminProductVariantController.destroy.url({
                product: product.id,
                variant: variantId,
            }),
            keepPageState,
        );
    }
</script>

{#snippet headerActions()}
    <ProductActions {product} showEdit={false} />
    <Button onclick={save} disabled={form.processing}>
        {form.processing ? 'Speichert…' : 'Speichern'}
    </Button>
{/snippet}

<AppHead title="{product.name} bearbeiten — Admin" />

<div class="flex h-full flex-1 flex-col gap-6 p-4">
    <h1 class="sr-only">{product.name} bearbeiten</h1>

    <Tabs.Root value="stammdaten">
        <Tabs.List>
            <Tabs.Trigger value="stammdaten">Stammdaten</Tabs.Trigger>
            <Tabs.Trigger value="bilder">
                Bilder
                {#if images.length > 0}
                    <span
                        class="ml-1.5 rounded-full bg-muted px-1.5 py-0.5 text-xs font-medium tabular-nums"
                    >
                        {images.length}
                    </span>
                {/if}
            </Tabs.Trigger>
            <Tabs.Trigger value="variationen">
                Variationen
                {#if variants.length > 0}
                    <span
                        class="ml-1.5 rounded-full bg-muted px-1.5 py-0.5 text-xs font-medium tabular-nums"
                    >
                        {variants.length}
                    </span>
                {/if}
            </Tabs.Trigger>
            <Tabs.Trigger value="bewertungen">
                Bewertungen
                {#if ratings.length > 0}
                    <span
                        class="ml-1.5 rounded-full bg-muted px-1.5 py-0.5 text-xs font-medium tabular-nums"
                    >
                        {ratings.length}
                    </span>
                {/if}
            </Tabs.Trigger>
        </Tabs.List>

        <!-- Stammdaten -->
        <Tabs.Content value="stammdaten">
            <form onsubmit={submit} class="mt-6 flex max-w-2xl flex-col gap-6">
                <div class="flex flex-col gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        bind:value={form.name}
                        placeholder="Produktname"
                    />
                    {#if form.errors.name}
                        <p class="text-sm text-destructive">
                            {form.errors.name}
                        </p>
                    {/if}
                </div>

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
                        <p class="text-sm text-destructive">
                            {form.errors.price}
                        </p>
                    {/if}
                </div>

                <div class="flex flex-col gap-2">
                    <Label>Kategorie</Label>
                    <Select.Root type="single" bind:value={form.category_slug}>
                        <Select.Trigger class="w-full">
                            {selectedCategoryLabel}
                        </Select.Trigger>
                        <Select.Content>
                            {#each categories as c (c.slug)}
                                <Select.Item value={c.slug}
                                    >{c.name}</Select.Item
                                >
                            {/each}
                        </Select.Content>
                    </Select.Root>
                    {#if form.errors.category_slug}
                        <p class="text-sm text-destructive">
                            {form.errors.category_slug}
                        </p>
                    {/if}
                </div>

                {#if hasSpecialties}
                    <SpecialtyPicker
                        bind:selected={form.specialties}
                        options={specialties}
                        errors={form.errors}
                    />
                {/if}

                <div class="flex flex-col gap-2">
                    <Label>Hersteller</Label>
                    <Select.Root
                        type="single"
                        bind:value={form.manufacturer_id}
                    >
                        <Select.Trigger class="w-full">
                            {selectedManufacturerLabel}
                        </Select.Trigger>
                        <Select.Content>
                            {#each manufacturers as m (m.id)}
                                <Select.Item value={String(m.id)}
                                    >{m.name}</Select.Item
                                >
                            {/each}
                        </Select.Content>
                    </Select.Root>
                    {#if form.errors.manufacturer_id}
                        <p class="text-sm text-destructive">
                            {form.errors.manufacturer_id}
                        </p>
                    {/if}
                </div>

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
                        <p class="text-sm text-destructive">
                            {form.errors.description}
                        </p>
                    {/if}
                </div>

                <TechnicalSpecsEditor
                    bind:specs={form.technical_specs}
                    errors={form.errors}
                />

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

                <div class="flex gap-3">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Speichert…' : 'Speichern'}
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
        </Tabs.Content>

        <!-- Bilder -->
        <Tabs.Content value="bilder">
            <div class="mt-6 flex max-w-2xl flex-col gap-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold">Produktbilder</h2>
                        <p class="text-sm text-muted-foreground">
                            {images.length}/5 Bilder · Reihenfolge per Drag
                            &amp; Drop ändern
                        </p>
                    </div>
                    <label
                        class={cn(
                            'flex cursor-pointer items-center gap-2 rounded-md border border-input bg-background px-3 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-accent',
                            (images.length >= 5 || uploading) &&
                                'pointer-events-none opacity-50',
                        )}
                    >
                        <ImagePlus class="size-4" />
                        {uploading ? 'Lädt hoch…' : 'Bild hinzufügen'}
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            disabled={images.length >= 5 || uploading}
                            onchange={uploadImage}
                        />
                    </label>
                </div>

                {#if images.length === 0}
                    <div
                        class="flex h-32 items-center justify-center rounded-lg border border-dashed border-input text-sm text-muted-foreground"
                    >
                        Noch keine Bilder hochgeladen
                    </div>
                {:else}
                    <div
                        role="list"
                        class="grid grid-cols-2 gap-3 sm:grid-cols-3"
                    >
                        {#each images as image (image.id)}
                            <div
                                role="listitem"
                                draggable="true"
                                ondragstart={() => onDragStart(image.id)}
                                ondragover={(e) => onDragOver(e, image.id)}
                                ondrop={(e) => onDrop(e, image.id)}
                                ondragend={onDragEnd}
                                class={cn(
                                    'group relative overflow-hidden rounded-lg border border-input bg-muted transition-opacity',
                                    draggedId === image.id && 'opacity-40',
                                    dragOverId === image.id &&
                                        draggedId !== image.id &&
                                        'ring-2 ring-primary',
                                )}
                            >
                                <img
                                    src={image.url}
                                    alt=""
                                    class="aspect-square w-full object-cover"
                                    draggable="false"
                                />

                                <div
                                    class="absolute left-1 top-1 cursor-grab rounded bg-black/50 p-0.5 text-white opacity-0 transition-opacity group-hover:opacity-100"
                                >
                                    <GripVertical class="size-4" />
                                </div>

                                {#if image.sort_order === 0}
                                    <div
                                        class="absolute bottom-1 left-1 flex items-center gap-1 rounded bg-primary px-1.5 py-0.5 text-xs font-medium text-primary-foreground"
                                    >
                                        <Star class="size-3" />
                                        Hauptbild
                                    </div>
                                {/if}

                                <button
                                    type="button"
                                    onclick={() => deleteImage(image.id)}
                                    class="absolute right-1 top-1 rounded bg-black/50 p-1 text-white opacity-0 transition-opacity hover:bg-destructive group-hover:opacity-100"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        {/each}
                    </div>
                {/if}
            </div>
        </Tabs.Content>

        <!-- Variationen -->
        <Tabs.Content value="variationen">
            <div class="mt-6 flex max-w-2xl flex-col gap-4">
                <div>
                    <h2 class="text-base font-semibold">Variationen</h2>
                    <p class="text-sm text-muted-foreground">
                        Optionale Preisvarianten — z.B. Packungsgrößen. Ohne
                        Variationen gilt der Produktpreis.
                    </p>
                </div>

                {#if variants.length > 0}
                    <div class="flex flex-col gap-2">
                        {#each variants as variant (variant.id)}
                            {#if editingVariantId === variant.id}
                                <div
                                    class="flex items-center gap-2 rounded-lg border border-primary bg-muted/40 p-3"
                                >
                                    <Input
                                        class="flex-1"
                                        bind:value={editVariant.label}
                                        placeholder="Label"
                                    />
                                    <Input
                                        class="w-28"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        bind:value={editVariant.price}
                                        placeholder="Preis"
                                    />
                                    <label
                                        class="flex cursor-pointer select-none items-center gap-1.5 text-sm text-muted-foreground"
                                    >
                                        <input
                                            type="checkbox"
                                            bind:checked={
                                                editVariant.is_default
                                            }
                                            class="accent-primary"
                                        />
                                        Standard
                                    </label>
                                    <Button
                                        size="sm"
                                        onclick={() => saveVariant(variant.id)}
                                        >Speichern</Button
                                    >
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onclick={() =>
                                            (editingVariantId = null)}
                                        >Abbrechen</Button
                                    >
                                </div>
                            {:else}
                                <div
                                    class="flex items-center gap-3 rounded-lg border bg-card px-4 py-2.5"
                                >
                                    <span class="flex-1 text-sm font-medium"
                                        >{variant.label}</span
                                    >
                                    {#if variant.is_default}
                                        <span
                                            class="flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                                        >
                                            <Check class="size-3" /> Standard
                                        </span>
                                    {/if}
                                    <span
                                        class="w-20 text-right text-sm tabular-nums text-muted-foreground"
                                        >{Number(variant.price).toFixed(2)} €</span
                                    >
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onclick={() =>
                                            startEditVariant(variant)}
                                        >Bearbeiten</Button
                                    >
                                    <button
                                        type="button"
                                        onclick={() =>
                                            deleteVariant(variant.id)}
                                        class="rounded p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            {/if}
                        {/each}
                    </div>
                {:else}
                    <div
                        class="flex h-20 items-center justify-center rounded-lg border border-dashed border-input text-sm text-muted-foreground"
                    >
                        Noch keine Variationen angelegt
                    </div>
                {/if}

                <div
                    class="flex items-center gap-2 rounded-lg border bg-muted/40 p-3"
                >
                    <Input
                        class="flex-1"
                        bind:value={newVariant.label}
                        placeholder="Label (z.B. 8er-Pack)"
                    />
                    <Input
                        class="w-28"
                        type="number"
                        step="0.01"
                        min="0"
                        bind:value={newVariant.price}
                        placeholder="Preis"
                    />
                    <label
                        class="flex cursor-pointer select-none items-center gap-1.5 text-sm text-muted-foreground"
                    >
                        <input
                            type="checkbox"
                            bind:checked={newVariant.is_default}
                            class="accent-primary"
                        />
                        Standard
                    </label>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onclick={addVariant}
                        disabled={!newVariant.label || !newVariant.price}
                    >
                        <Plus class="size-4" />
                        Hinzufügen
                    </Button>
                </div>
            </div>
        </Tabs.Content>

        <Tabs.Content value="bewertungen">
            <div class="mt-6 flex max-w-2xl flex-col gap-4">
                <div>
                    <h2 class="text-base font-semibold">Bewertungen</h2>
                    <p class="text-sm text-muted-foreground">
                        Bewertungen von Käufern sind sofort öffentlich. Hier
                        lassen sich unpassende löschen.
                    </p>
                </div>

                {#if ratings.length > 0}
                    <div class="flex flex-col divide-y rounded-lg border">
                        {#each ratings as rating (rating.id)}
                            <div
                                class="flex items-start justify-between gap-4 p-4"
                            >
                                <div class="min-w-0 space-y-1">
                                    <p class="text-sm">
                                        <span class="font-medium"
                                            >{rating.stars} von 5 Sternen</span
                                        >
                                        {#if rating.created_at}
                                            <span class="text-muted-foreground">
                                                · {rating.created_at}</span
                                            >
                                        {/if}
                                        <span class="text-muted-foreground">
                                            · {rating.verified
                                                ? 'Verifizierter Kauf'
                                                : 'ohne Kaufbezug (Altbestand)'}</span
                                        >
                                    </p>
                                    <p
                                        class="text-sm whitespace-pre-line text-muted-foreground"
                                    >
                                        {rating.content}
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="shrink-0 text-destructive"
                                    onclick={() => deleteRating(rating.id)}
                                >
                                    Löschen
                                </Button>
                            </div>
                        {/each}
                    </div>
                {:else}
                    <p class="text-sm text-muted-foreground">
                        Noch keine Bewertungen.
                    </p>
                {/if}
            </div>
        </Tabs.Content>
    </Tabs.Root>
</div>
