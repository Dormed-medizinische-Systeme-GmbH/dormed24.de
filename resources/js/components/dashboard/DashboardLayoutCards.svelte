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

<!-- Variante A: zwei Kennzahlen-Karten, darunter das Diagramm (bisheriges Layout) -->
<div class="grid gap-4 md:grid-cols-2">
    <Card>
        <CardHeader>
            <CardDescription>Gesamtumsatz</CardDescription>
            <CardTitle class="text-3xl tabular-nums">
                {formatPrice(totals.revenue)}
            </CardTitle>
        </CardHeader>
    </Card>

    <Card>
        <CardHeader>
            <CardDescription>Bestellungen gesamt</CardDescription>
            <CardTitle class="text-3xl tabular-nums">
                {totals.orders.toLocaleString('de-DE')}
            </CardTitle>
        </CardHeader>
    </Card>
</div>

<Card>
    <CardHeader
        class="flex items-center gap-2 space-y-0 border-b py-5 sm:flex-row"
    >
        <div class="grid flex-1 gap-1 text-center sm:text-start">
            <CardTitle>Statistiken</CardTitle>
            <CardDescription>{dateRange}</CardDescription>
        </div>
        {@render rangePopover()}
    </CardHeader>
    <CardContent class="pt-6">
        {@render chart()}
    </CardContent>
    {#if stats.trendPercent !== null}
        <CardFooter>
            {@render trend()}
        </CardFooter>
    {/if}
</Card>
