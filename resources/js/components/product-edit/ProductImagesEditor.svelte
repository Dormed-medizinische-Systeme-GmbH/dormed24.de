<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { GripVertical, Plus, Star, Trash2 } from '@lucide/svelte';
    import { keepPageState } from '@/lib/productEdit';
    import { cn } from '@/lib/utils';
    import type { ProductImage } from '@/types';

    const MAX_IMAGES = 5;

    let {
        productId,
        images: imageProps,
    }: {
        productId: number;
        images: ProductImage[];
    } = $props();

    // Lokale, editierbare Kopie der Bilder: re-derived, sobald sich die
    // images-Prop aendert (Upload/Loeschen), lokal per Drag-Reorder
    // ueberschreibbar (writable $derived).
    let images = $derived([...imageProps]);
    let uploading = $state(false);
    let draggedId = $state<number | null>(null);
    let dragOverId = $state<number | null>(null);

    // Immer nur eine freie „+“-Karte, solange noch Platz ist.
    const canAddImage = $derived(images.length < MAX_IMAGES);

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

<div class="flex flex-col gap-2">
    <!-- Bilder in einem Fünfer-Raster, dahinter eine „+“-Karte zum Hochladen. -->
    <div role="list" class="grid grid-cols-5 gap-2">
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
                        class="absolute bottom-1 left-1 flex items-center rounded bg-primary p-1 text-primary-foreground"
                        title="Hauptbild"
                    >
                        <Star class="size-3" aria-hidden="true" />
                        <span class="sr-only">Hauptbild</span>
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

        {#if canAddImage}
            <label
                class={cn(
                    'flex aspect-square cursor-pointer items-center justify-center rounded-lg border border-dashed border-input text-muted-foreground transition-colors hover:bg-accent',
                    uploading && 'pointer-events-none opacity-50',
                )}
            >
                <Plus class="size-5" aria-hidden="true" />
                <span class="sr-only">Bild hinzufügen</span>
                <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="sr-only"
                    disabled={uploading}
                    onchange={uploadImage}
                />
            </label>
        {/if}
    </div>

    <p class="text-xs text-muted-foreground">
        {uploading ? 'Lädt hoch…' : `${images.length}/${MAX_IMAGES} Bilder`} · Reihenfolge
        per Drag &amp; Drop ändern, das erste Bild ist das Hauptbild
    </p>
</div>
