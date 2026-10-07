<script lang="ts">
    import * as RatingController from '@/actions/App/Http/Controllers/RatingController';
    import InputError from '@/components/InputError.svelte';
    import StarRatingInput from '@/components/StarRatingInput.svelte';
    import { Button } from '@/components/ui/button';
    import { Label } from '@/components/ui/label';
    import { Textarea } from '@/components/ui/textarea';
    import { fetchJson } from '@/lib/http';

    type SubmittedRating = { id: number; stars: number; content: string };

    let {
        productId,
        onSubmitted,
    }: {
        productId: number;
        onSubmitted: (rating: SubmittedRating) => void;
    } = $props();

    let stars = $state(5);
    let content = $state('');
    let processing = $state(false);
    let errors = $state<Record<string, string>>({});

    const fieldId = $derived(`rating-${productId}`);

    async function submit(event: SubmitEvent) {
        event.preventDefault();
        processing = true;
        errors = {};

        const { ok, status, data } = await fetchJson<{
            rating?: SubmittedRating;
            errors?: Record<string, string[]>;
        }>(RatingController.store.url(productId), {
            method: 'POST',
            body: { stars, content },
        });

        processing = false;

        if (ok && data.rating) {
            content = '';
            onSubmitted(data.rating);

            return;
        }

        if (status === 422 && data.errors) {
            errors = Object.fromEntries(
                Object.entries(data.errors).map(([field, messages]) => [
                    field,
                    messages[0],
                ]),
            );

            return;
        }

        errors = {
            rating:
                status === 401 || status === 419
                    ? 'Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.'
                    : status === 403
                      ? 'Bewerten können Sie nur Produkte aus bezahlten Bestellungen.'
                      : 'Die Bewertung konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.',
        };
    }
</script>

<form class="space-y-4" onsubmit={submit}>
    <div class="grid gap-2">
        <Label for={`${fieldId}-stars`}>Sterne</Label>
        <StarRatingInput id={`${fieldId}-stars`} bind:value={stars} />
        <InputError message={errors.stars} />
    </div>

    <div class="grid gap-2">
        <Label for={`${fieldId}-content`}>Bewertung</Label>
        <Textarea
            id={`${fieldId}-content`}
            name="content"
            rows={4}
            required
            minlength={5}
            maxlength={2000}
            bind:value={content}
            placeholder="Wie zufrieden sind Sie mit dem Produkt?"
        />
        <InputError message={errors.content} />
    </div>

    <InputError message={errors.rating} />

    <Button type="submit" class="w-full" disabled={processing}>
        {processing ? 'Wird gesendet …' : 'Bewertung senden'}
    </Button>
</form>
