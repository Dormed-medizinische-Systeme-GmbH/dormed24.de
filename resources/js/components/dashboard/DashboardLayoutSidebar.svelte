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

<!-- Variante C: Kennzahlen als Spalte links, Diagramm rechts -->
<div class="grid items-start gap-4 lg:grid-cols-[16rem_minmax(0,1fr)]">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
        <Card class="gap-2 py-5">
            <CardHeader>
                <CardDescription>Gesamtumsatz</CardDescription>
                <CardTitle class="text-2xl tabular-nums">
                    {formatPrice(totals.revenue)}
                </CardTitle>
            </CardHeader>
        </Card>
        <Card class="gap-2 py-5">
            <CardHeader>
                <CardDescription>Bestellungen gesamt</CardDescription>
                <CardTitle class="text-2xl tabular-nums">
                    {totals.orders.toLocaleString('de-DE')}
                </CardTitle>
            </CardHeader>
        </Card>
        <Card class="gap-2 py-5">
            <CardHeader>
                <CardDescription>Umsatz im Zeitraum</CardDescription>
                <CardTitle class="text-2xl tabular-nums">
                    {formatPrice(stats.periodRevenue)}
                </CardTitle>
            </CardHeader>
        </Card>
        <Card class="gap-2 py-5">
            <CardHeader>
                <CardDescription>Bestellungen im Zeitraum</CardDescription>
                <CardTitle class="text-2xl tabular-nums">
                    {stats.periodOrders.toLocaleString('de-DE')}
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
</div>
