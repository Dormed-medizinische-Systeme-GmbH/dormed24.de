<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { Check, Plus, Trash2 } from '@lucide/svelte';
    import * as AdminProductVariantController from '@/actions/App/Http/Controllers/Admin/ProductVariantController';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { keepPageState } from '@/lib/productEdit';
    import type { ProductVariant } from '@/types';

    let {
        productId,
        variants: variantProps,
        showHeader = true,
    }: {
        productId: number;
        variants: ProductVariant[];
        /** `false`, wenn das Layout die Überschrift selbst liefert. */
        showHeader?: boolean;
    } = $props();

    const variants = $derived([...variantProps]);
    let newVariant = $state({ label: '', price: '', is_default: false });
    let editingVariantId = $state<number | null>(null);
    let editVariant = $state({ label: '', price: '', is_default: false });

    function addVariant() {
        if (!newVariant.label || !newVariant.price) {
            return;
        }

        router.post(
            AdminProductVariantController.store.url(productId),
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
                product: productId,
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
                product: productId,
                variant: variantId,
            }),
            keepPageState,
        );
    }
</script>

<div class="flex flex-col gap-4">
    {#if showHeader}
        <div>
            <h2 class="text-base font-semibold">Variationen</h2>
            <p class="text-sm text-muted-foreground">
                Optionale Preisvarianten — z.B. Packungsgrößen. Ohne Variationen
                gilt der Produktpreis.
            </p>
        </div>
    {/if}

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
                                bind:checked={editVariant.is_default}
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
                            onclick={() => (editingVariantId = null)}
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
                            onclick={() => startEditVariant(variant)}
                            >Bearbeiten</Button
                        >
                        <button
                            type="button"
                            aria-label="Variation löschen"
                            onclick={() => deleteVariant(variant.id)}
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

    <div class="flex items-center gap-2 rounded-lg border bg-muted/40 p-3">
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
