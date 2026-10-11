<script lang="ts">
    import type { CheckoutConfirmLayoutProps } from '@/types';

    let { fields }: CheckoutConfirmLayoutProps = $props();
</script>

<!-- Variante F: eine schmale Spalte, Adressen eingeklappt mit Kurzfassung -->
<div class="mx-auto flex w-full max-w-2xl flex-col gap-4">
    <div class="rounded-lg border bg-white p-5">
        {@render fields.totals()}
    </div>

    <details open class="group rounded-lg border bg-white">
        <summary
            class="flex cursor-pointer list-none items-center justify-between gap-4 p-5"
        >
            <span class="min-w-0">
                <span class="block font-bold text-foreground"
                    >Lieferadresse</span
                >
                <span class="block truncate text-sm text-muted-foreground">
                    {@render fields.shippingSummary()}
                </span>
            </span>
            <span class="text-sm text-brand-accent group-open:hidden"
                >Bearbeiten</span
            >
        </summary>
        <div class="border-t p-5">
            {@render fields.shippingAddress()}
        </div>
    </details>

    <details class="group rounded-lg border bg-white">
        <summary
            class="flex cursor-pointer list-none items-center justify-between gap-4 p-5"
        >
            <span class="min-w-0">
                <span class="block font-bold text-foreground"
                    >Rechnungsadresse</span
                >
                <span class="block truncate text-sm text-muted-foreground">
                    {@render fields.billingSummary()}
                </span>
            </span>
            <span class="text-sm text-brand-accent group-open:hidden"
                >Bearbeiten</span
            >
        </summary>
        <div class="border-t p-5">
            {@render fields.billingAddress()}
        </div>
    </details>

    <div class="rounded-lg border bg-white p-5">
        {@render fields.terms()}
    </div>

    <div class="rounded-lg border bg-white p-5">
        {@render fields.pay()}
    </div>
    {@render fields.note()}
</div>
