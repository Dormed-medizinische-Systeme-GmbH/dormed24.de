<script lang="ts">
    import type { CartPageLayoutProps } from '@/types';

    let { fields, isEmpty }: CartPageLayoutProps = $props();
</script>

<!-- Variante B: Positionen als Zeilen, Versand und Summe in einer mitlaufenden Karte -->
<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
    <div class="overflow-hidden rounded-lg border bg-white">
        {#if isEmpty}
            {@render fields.empty()}
        {:else}
            {@render fields.items('rows')}
        {/if}
    </div>

    <aside class="lg:sticky lg:top-6">
        <div class="rounded-lg border bg-white p-5">
            {@render fields.shipping()}
            <div class="my-5 border-t"></div>
            {@render fields.totals()}
            {@render fields.checkout()}
        </div>
        {@render fields.note()}
    </aside>
</div>
