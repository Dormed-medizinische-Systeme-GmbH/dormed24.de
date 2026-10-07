<script lang="ts">
    import Check from '@lucide/svelte/icons/check';
    import Star from '@lucide/svelte/icons/star';
    import RatingForm from '@/components/RatingForm.svelte';
    import { Button } from '@/components/ui/button';

    /**
     * Bewerten eines Artikels aus den Bestelldetails. Das Formular klappt
     * unter dem Artikel auf statt in einem Dialog, weil die Bestelldetails
     * selbst in einem Dialog stehen können.
     */
    let {
        productId,
        productName,
        rating,
    }: {
        productId: number;
        productName: string;
        rating: 'rateable' | 'rated' | null;
    } = $props();

    // Nach dem Absenden lokal auf "rated", ohne die Bestellung neu zu laden.
    // svelte-ignore state_referenced_locally
    let current = $state(rating);
    let open = $state(false);
</script>

{#if current === 'rated'}
    <p class="inline-flex items-center gap-1.5 text-sm text-emerald-700">
        <Check class="size-4" />
        Bewertet
    </p>
{:else if current === 'rateable'}
    {#if open}
        <div class="w-full rounded-xl border bg-muted/30 p-4">
            <p class="mb-3 text-sm font-medium">
                {productName} bewerten
            </p>
            <RatingForm
                {productId}
                onSubmitted={() => {
                    current = 'rated';
                    open = false;
                }}
            />
            <Button
                variant="ghost"
                size="sm"
                class="mt-2 w-full"
                onclick={() => (open = false)}
            >
                Abbrechen
            </Button>
        </div>
    {:else}
        <Button variant="outline" size="sm" onclick={() => (open = true)}>
            <Star class="size-4" />
            Bewerten
        </Button>
    {/if}
{/if}
