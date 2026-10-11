<script lang="ts">
    import type { CheckoutConfirmLayoutProps } from '@/types';

    let { fields }: CheckoutConfirmLayoutProps = $props();
</script>

<!-- Variante E: Adressen und AGB links, Summe rechts, die Zahlarten unten in voller Breite nebeneinander -->
<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        <div class="flex flex-1 flex-col gap-6">
            <div class="rounded-lg border bg-white p-5">
                {@render fields.shippingAddress()}
            </div>
            <div class="rounded-lg border bg-white p-5">
                {@render fields.billingAddress()}
            </div>
            <div class="rounded-lg border bg-white p-5">
                {@render fields.terms()}
            </div>
        </div>

        <div class="w-full lg:sticky lg:top-6 lg:w-80 xl:w-88">
            <div class="rounded-lg border bg-white p-5">
                {@render fields.totals()}
            </div>
            <div class="mt-3">
                {@render fields.note()}
            </div>
        </div>
    </div>

    <div class="rounded-lg border bg-white p-5">
        <h2 class="mb-4 text-xl font-bold text-foreground">
            Zahlungsart wählen
        </h2>
        {@render fields.pay(true)}
    </div>
</div>
