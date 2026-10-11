<script lang="ts">
    import type { CartPageLayoutProps } from '@/types';

    let { fields, isEmpty }: CartPageLayoutProps = $props();
</script>

<!-- Variante D: Versandart zuerst als Karten, darunter Tabelle und Summe -->
<div class="flex flex-col gap-6">
    <div class="rounded-lg border bg-white p-5">
        {@render fields.shipping('cards')}
    </div>

    <div class="flex flex-col gap-6 lg:flex-row lg:items-stretch">
        <div class="flex flex-1 flex-col">
            <div
                class="flex flex-1 flex-col overflow-hidden rounded-lg border bg-white"
            >
                {#if isEmpty}
                    {@render fields.empty()}
                {:else}
                    {@render fields.items('table')}
                {/if}
            </div>
        </div>

        <div class="w-full lg:w-80 xl:w-88">
            <div class="rounded-lg border bg-white p-5">
                {@render fields.totals()}
                {@render fields.checkout()}
            </div>
            {@render fields.note()}
        </div>
    </div>
</div>
