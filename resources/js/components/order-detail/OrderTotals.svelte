<script lang="ts">
    import { formatPrice } from '@/lib/currency';
    import type { OrderSummary } from '@/types';

    let {
        summary,
        class: className = '',
    }: { summary: OrderSummary; class?: string } = $props();
</script>

<dl class="space-y-2 text-sm {className}">
    <div class="flex items-center justify-between gap-4">
        <dt class="text-muted-foreground">Zwischensumme</dt>
        <dd class="font-medium">{formatPrice(summary.subtotal)}*</dd>
    </div>
    <div class="flex items-center justify-between gap-4">
        <dt class="text-muted-foreground">Versand</dt>
        <dd class="font-medium">{formatPrice(summary.shipping_total)}*</dd>
    </div>
    <div class="flex items-center justify-between gap-4">
        <dt class="text-muted-foreground">MwSt. ({summary.vat_rate} %)</dt>
        <dd class="font-medium">{formatPrice(summary.vat_amount)}*</dd>
    </div>
    <div
        class="flex items-center justify-between gap-4 border-t pt-3 text-lg font-semibold"
    >
        <dt>Gesamt</dt>
        <dd>{formatPrice(summary.total)}*</dd>
    </div>
</dl>
