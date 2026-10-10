<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import * as AdminRatingController from '@/actions/App/Http/Controllers/Admin/RatingController';
    import { Button } from '@/components/ui/button';
    import { keepPageState } from '@/lib/productEdit';
    import type { ProductRating } from '@/types';

    let {
        ratings,
        showHeader = true,
    }: {
        ratings: ProductRating[];
        /** `false`, wenn das Layout die Überschrift selbst liefert. */
        showHeader?: boolean;
    } = $props();

    function deleteRating(ratingId: number) {
        if (
            !confirm(
                'Bewertung wirklich löschen? Sie verschwindet sofort von der Produktseite.',
            )
        ) {
            return;
        }

        router.delete(
            AdminRatingController.destroy.url(ratingId),
            keepPageState,
        );
    }
</script>

<div class="flex flex-col gap-4">
    {#if showHeader}
        <div>
            <h2 class="text-base font-semibold">Bewertungen</h2>
            <p class="text-sm text-muted-foreground">
                Bewertungen von Käufern sind sofort öffentlich. Hier lassen sich
                unpassende löschen.
            </p>
        </div>
    {/if}

    {#if ratings.length > 0}
        <div class="flex flex-col divide-y rounded-lg border">
            {#each ratings as rating (rating.id)}
                <div class="flex items-start justify-between gap-4 p-4">
                    <div class="min-w-0 space-y-1">
                        <p class="text-sm">
                            <span class="font-medium"
                                >{rating.stars} von 5 Sternen</span
                            >
                            {#if rating.created_at}
                                <span class="text-muted-foreground">
                                    · {rating.created_at}</span
                                >
                            {/if}
                            <span class="text-muted-foreground">
                                · {rating.verified
                                    ? 'Verifizierter Kauf'
                                    : 'ohne Kaufbezug (Altbestand)'}</span
                            >
                        </p>
                        <p
                            class="text-sm whitespace-pre-line text-muted-foreground"
                        >
                            {rating.content}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        class="shrink-0 text-destructive"
                        onclick={() => deleteRating(rating.id)}
                    >
                        Löschen
                    </Button>
                </div>
            {/each}
        </div>
    {:else}
        <p class="text-sm text-muted-foreground">Noch keine Bewertungen.</p>
    {/if}
</div>
