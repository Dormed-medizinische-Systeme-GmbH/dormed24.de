<script lang="ts">
    import { formatPrice } from '@/lib/currency';
    import type { DashboardLayoutProps } from '@/types';

    let {
        totals,
        stats,
        dateRange,
        rangeSegments,
        chart,
        trend,
    }: DashboardLayoutProps = $props();
</script>

<!-- Variante D: Zeitraum als Segmente, Kennzahlen als Fließtext-Leiste, Diagramm ohne Karte -->
<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Dashboard</h1>
            <p class="text-sm text-muted-foreground">{dateRange}</p>
        </div>
        {@render rangeSegments()}
    </div>

    <dl class="flex flex-wrap gap-x-10 gap-y-4 border-y py-4">
        <div>
            <dt class="text-xs text-muted-foreground">Gesamtumsatz</dt>
            <dd class="text-xl font-semibold tabular-nums">
                {formatPrice(totals.revenue)}
            </dd>
        </div>
        <div>
            <dt class="text-xs text-muted-foreground">Bestellungen gesamt</dt>
            <dd class="text-xl font-semibold tabular-nums">
                {totals.orders.toLocaleString('de-DE')}
            </dd>
        </div>
        <div>
            <dt class="text-xs text-muted-foreground">Umsatz im Zeitraum</dt>
            <dd class="text-xl font-semibold tabular-nums">
                {formatPrice(stats.periodRevenue)}
            </dd>
        </div>
        <div>
            <dt class="text-xs text-muted-foreground">
                Bestellungen im Zeitraum
            </dt>
            <dd class="text-xl font-semibold tabular-nums">
                {stats.periodOrders.toLocaleString('de-DE')}
            </dd>
        </div>
    </dl>

    {@render chart()}

    {#if stats.trendPercent !== null}
        <div class="rounded-lg bg-muted/50 px-4 py-3">
            {@render trend()}
        </div>
    {/if}
</div>
