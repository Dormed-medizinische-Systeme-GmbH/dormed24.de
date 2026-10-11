<script lang="ts">
    import type { CheckoutConfirmLayoutProps } from '@/types';

    let { fields }: CheckoutConfirmLayoutProps = $props();
</script>

<!-- Variante B: eine Spalte, Adressen nebeneinander, unten Summe und Zahlung -->
<div class="mx-auto flex max-w-4xl flex-col gap-6">
    <div class="grid gap-6 md:grid-cols-2 md:items-start">
        <div class="rounded-lg border bg-white p-5">
            {@render fields.shippingAddress()}
        </div>
        <div class="rounded-lg border bg-white p-5">
            {@render fields.billingAddress()}
        </div>
    </div>

    <div class="rounded-lg border bg-white p-5">
        {@render fields.terms()}
    </div>

    <div class="grid gap-6 md:grid-cols-2 md:items-start">
        <div class="rounded-lg border bg-white p-5">
            {@render fields.totals()}
        </div>
        <div class="rounded-lg border bg-white p-5">
            {@render fields.pay()}
        </div>
    </div>
    {@render fields.note()}
</div>
