<script lang="ts">
    import {
        Card,
        CardContent,
        CardDescription,
        CardFooter,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { formatPrice } from '@/lib/currency';
    import type { DashboardLayoutProps } from '@/types';

    let {
        totals,
        stats,
        dateRange,
        rangePopover,
        chart,
        trend,
    }: DashboardLayoutProps = $props();
</script>

<!-- Variante B: eine große Karte, Kennzahlen als Leiste über dem Diagramm -->
<Card>
    <CardHeader class="flex items-center gap-2 space-y-0 sm:flex-row">
        <div class="grid flex-1 gap-1 text-center sm:text-start">
            <CardTitle>Übersicht</CardTitle>
            <CardDescription>{dateRange}</CardDescription>
        </div>
        {@render rangePopover()}
    </CardHeader>

    <dl
        class="mx-6 grid grid-cols-2 gap-px overflow-hidden rounded-lg border bg-border lg:grid-cols-4"
    >
        <div class="bg-card p-4">
            <dt class="text-xs text-muted-foreground">Gesamtumsatz</dt>
            <dd class="text-2xl font-semibold tabular-nums">
                {formatPrice(totals.revenue)}
            </dd>
        </div>
        <div class="bg-card p-4">
            <dt class="text-xs text-muted-foreground">Bestellungen gesamt</dt>
            <dd class="text-2xl font-semibold tabular-nums">
                {totals.orders.toLocaleString('de-DE')}
            </dd>
        </div>
        <div class="bg-card p-4">
            <dt class="text-xs text-muted-foreground">Umsatz im Zeitraum</dt>
            <dd class="text-2xl font-semibold tabular-nums">
                {formatPrice(stats.periodRevenue)}
            </dd>
        </div>
        <div class="bg-card p-4">
            <dt class="text-xs text-muted-foreground">Ø Bestellwert</dt>
            <dd class="text-2xl font-semibold tabular-nums">
                {stats.averageOrderValue === null
                    ? '—'
                    : formatPrice(stats.averageOrderValue)}
            </dd>
        </div>
    </dl>

    <CardContent>
        {@render chart()}
    </CardContent>
    {#if stats.trendPercent !== null}
        <CardFooter>
            {@render trend()}
        </CardFooter>
    {/if}
</Card>
