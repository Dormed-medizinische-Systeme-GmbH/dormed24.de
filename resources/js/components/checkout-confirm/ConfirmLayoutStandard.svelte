<script lang="ts">
    import type { CheckoutConfirmLayoutProps } from '@/types';

    let { fields }: CheckoutConfirmLayoutProps = $props();
</script>

<!-- Variante A: Adressen links, AGB, Summe und Zahlung in der Seitenleiste -->
<div class="flex flex-col gap-6 lg:flex-row lg:items-start">
    <div class="flex flex-1 flex-col gap-6">
        <div class="rounded-lg border bg-white p-5">
            {@render fields.shippingAddress()}
        </div>
        <div class="rounded-lg border bg-white p-5">
            {@render fields.billingAddress()}
        </div>
    </div>

    <div class="flex w-full flex-col gap-6 lg:w-80 xl:w-88">
        <div class="rounded-lg border bg-white p-5">
            {@render fields.terms()}
        </div>
        <div class="rounded-lg border bg-white p-5">
            {@render fields.totals()}
            <div class="mt-6">
                {@render fields.pay()}
            </div>
        </div>
        <div class="-mt-3">
            {@render fields.note()}
        </div>
    </div>
</div>
