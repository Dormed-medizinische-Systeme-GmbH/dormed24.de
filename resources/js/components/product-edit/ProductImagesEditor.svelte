<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { GripVertical, ImagePlus, Star, Trash2 } from '@lucide/svelte';
    import { keepPageState } from '@/lib/productEdit';
    import { cn } from '@/lib/utils';
    import type { ProductImage } from '@/types';

    const MAX_IMAGES = 5;

    const columnClasses = {
        2: 'grid-cols-2',
        3: 'grid-cols-2 sm:grid-cols-3',
        4: 'grid-cols-2 sm:grid-cols-4',
        5: 'grid-cols-3 sm:grid-cols-5',
    } as const;

    let {
        productId,
        images: imageProps,
        columns = 3,
        showHeader = true,
    }: {
        productId: number;
        images: ProductImage[];
        columns?: keyof typeof columnClasses;
        /** `false`, wenn das Layout die Überschrift selbst liefert. */
        showHeader?: boolean;
    } = $props();

    // Lokale, editierbare Kopie der Bilder: re-derived, sobald sich die
    // images-Prop aendert (Upload/Loeschen), lokal per Drag-Reorder
    // ueberschreibbar (writable $derived).
    let images = $derived([...imageProps]);
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

        router.post(`/admin/products/${productId}/images`, data, {
            ...keepPageState,
            onFinish: () => {
                uploading = false;
                input.value = '';
            },
        });
    }

    function deleteImage(imageId: number) {
        if (!confirm('Bild wirklich löschen?')) {
            return;
        }

        router.delete(
            `/admin/products/${productId}/images/${imageId}`,
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
            `/admin/products/${productId}/images/reorder`,
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
</script>

<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            {#if showHeader}
                <h2 class="text-base font-semibold">Produktbilder</h2>
            {/if}
            <p class="text-sm text-muted-foreground">
                {images.length}/{MAX_IMAGES} Bilder · Reihenfolge per Drag &amp; Drop
                ändern
            </p>
        </div>
        <label
            class={cn(
                'flex shrink-0 cursor-pointer items-center gap-2 rounded-md border border-input bg-background px-3 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-accent',
                (images.length >= MAX_IMAGES || uploading) &&
                    'pointer-events-none opacity-50',
            )}
        >
            <ImagePlus class="size-4" />
            {uploading ? 'Lädt hoch…' : 'Bild hinzufügen'}
            <input
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="sr-only"
                disabled={images.length >= MAX_IMAGES || uploading}
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
        <div role="list" class={cn('grid gap-3', columnClasses[columns])}>
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
                        aria-label="Bild löschen"
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
