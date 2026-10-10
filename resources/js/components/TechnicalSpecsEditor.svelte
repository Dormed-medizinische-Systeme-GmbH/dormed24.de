<script lang="ts">
    import { Plus, Trash2 } from '@lucide/svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    export type TechnicalSpec = { label: string; value: string };

    let {
        specs = $bindable(),
        errors = {},
        hideHeader = false,
    }: {
        specs: TechnicalSpec[];
        errors?: Partial<Record<string, string>>;
        /** `true`, wenn das Layout die Überschrift selbst liefert. */
        hideHeader?: boolean;
    } = $props();

    const rowErrors = $derived(
        Object.entries(errors).filter(([key]) =>
            key.startsWith('technical_specs'),
        ),
    );

    function addRow() {
        specs = [...specs, { label: '', value: '' }];
    }

    function removeRow(index: number) {
        specs = specs.filter((_, i) => i !== index);
    }
</script>

<div class="flex flex-col gap-2">
    {#if !hideHeader}
        <div>
            <Label>Technische Daten</Label>
            <p class="text-xs text-muted-foreground">
                Erscheint im Shop im Reiter „Technische Daten“. Leere Zeilen
                werden beim Speichern ignoriert.
            </p>
        </div>
    {/if}

    {#each specs as spec, index (index)}
        <div class="flex items-center gap-2">
            <Input
                class="w-2/5"
                bind:value={spec.label}
                placeholder="Bezeichnung (z.B. Gewicht)"
                aria-label="Bezeichnung"
            />
            <Input
                class="flex-1"
                bind:value={spec.value}
                placeholder="Wert (z.B. 2,4 kg)"
                aria-label="Wert"
            />
            <button
                type="button"
                onclick={() => removeRow(index)}
                aria-label="Zeile entfernen"
                class="rounded p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
            >
                <Trash2 class="size-4" />
            </button>
        </div>
    {/each}

    <div>
        <Button type="button" variant="outline" size="sm" onclick={addRow}>
            <Plus class="size-4" />
            Eigenschaft hinzufügen
        </Button>
    </div>

    {#each rowErrors as [key, message] (key)}
        <p class="text-sm text-destructive">{message}</p>
    {/each}
</div>
