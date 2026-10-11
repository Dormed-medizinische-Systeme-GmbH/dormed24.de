<script lang="ts">
    import type { CartPageLayoutProps } from '@/types';

    let { fields, isEmpty }: CartPageLayoutProps = $props();
</script>

<!-- Variante E: Positionen und Versand untereinander, Gesamtsumme und Kasse in einer Leiste, die unten mitläuft -->
<div class="flex flex-col gap-6">
    <div class="overflow-hidden rounded-lg border bg-white">
        {#if isEmpty}
            {@render fields.empty()}
        {:else}
            {@render fields.items('rows')}
        {/if}
    </div>

    <div class="grid gap-6 md:grid-cols-2 md:items-start">
        <div class="rounded-lg border bg-white p-5">
            {@render fields.shipping('cards')}
        </div>
        <div class="rounded-lg border bg-white p-5">
            {@render fields.totals()}
        </div>
    </div>

    <!-- bottom-16: lässt unten Platz für die Varianten-Pille -->
    <div
        class="sticky bottom-16 z-30 flex flex-col gap-3 rounded-lg border bg-white p-4 shadow-lg sm:flex-row sm:items-center sm:justify-between"
    >
        {@render fields.totalLine()}
        <div class="sm:w-60">
            {@render fields.checkout()}
        </div>
    </div>
    {@render fields.note()}
</div>
