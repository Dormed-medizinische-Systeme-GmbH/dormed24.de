<script lang="ts">
    import type { CartPageLayoutProps } from '@/types';

    let { fields, isEmpty }: CartPageLayoutProps = $props();
</script>

<!-- Variante F: alles in einer schmalen Beleg-Karte -->
<div class="mx-auto w-full max-w-2xl">
    <div class="overflow-hidden rounded-lg border bg-white">
        {#if isEmpty}
            {@render fields.empty()}
        {:else}
            {@render fields.items('rows')}
        {/if}

        <div class="border-t bg-muted/40 p-5">
            {@render fields.shipping()}
        </div>

        <div class="border-t p-5">
            {@render fields.totals()}
            {@render fields.checkout()}
        </div>
    </div>
    {@render fields.note()}
</div>
