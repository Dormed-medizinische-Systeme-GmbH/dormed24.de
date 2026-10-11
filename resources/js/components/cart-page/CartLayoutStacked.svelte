<script lang="ts">
    import type { CartPageLayoutProps } from '@/types';

    let { fields, isEmpty }: CartPageLayoutProps = $props();
</script>

<!-- Variante C: eine Spalte, darunter Versand und Summe nebeneinander -->
<div class="mx-auto flex max-w-4xl flex-col gap-6">
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
            {@render fields.checkout()}
        </div>
    </div>
    {@render fields.note()}
</div>
