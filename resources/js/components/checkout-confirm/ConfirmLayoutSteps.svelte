<script lang="ts">
    import type { Snippet } from 'svelte';
    import type { CheckoutConfirmLayoutProps } from '@/types';

    let { fields }: CheckoutConfirmLayoutProps = $props();
</script>

<!-- Variante D: vier nummerierte Schritte links, Summe läuft rechts mit -->
{#snippet step(number: number, title: string, content: Snippet)}
    <li class="flex gap-4">
        <span
            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-white"
            aria-hidden="true"
        >
            {number}
        </span>
        <div
            class="min-w-0 flex-1 rounded-lg border bg-white p-5"
            aria-label="Schritt {number}: {title}"
        >
            {@render content()}
        </div>
    </li>
{/snippet}

{#snippet shippingStep()}{@render fields.shippingAddress()}{/snippet}
{#snippet billingStep()}{@render fields.billingAddress()}{/snippet}
{#snippet termsStep()}{@render fields.terms()}{/snippet}
{#snippet payStep()}
    <h2 class="mb-2 font-bold text-foreground">Zahlung</h2>
    <div class="mt-4">
        {@render fields.pay()}
    </div>
{/snippet}

<div class="flex flex-col gap-6 lg:flex-row lg:items-start">
    <ol class="flex flex-1 flex-col gap-6">
        {@render step(1, 'Lieferadresse', shippingStep)}
        {@render step(2, 'Rechnungsadresse', billingStep)}
        {@render step(3, 'AGB', termsStep)}
        {@render step(4, 'Zahlung', payStep)}
    </ol>

    <aside class="w-full lg:sticky lg:top-6 lg:w-80 xl:w-88">
        <div class="rounded-lg border bg-white p-5">
            {@render fields.totals()}
        </div>
        <div class="mt-3">
            {@render fields.note()}
        </div>
    </aside>
</div>
