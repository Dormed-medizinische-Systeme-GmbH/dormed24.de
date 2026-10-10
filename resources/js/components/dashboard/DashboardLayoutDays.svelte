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
    import { formatDate } from '@/lib/date';
    import type { DashboardLayoutProps } from '@/types';

    let {
        totals,
        stats,
        days,
        dateRange,
        rangePopover,
        chart,
        trend,
    }: DashboardLayoutProps = $props();

    // Neueste Tage zuerst.
    const rows = $derived([...days].reverse());
</script>

<!-- Variante E: Diagramm links, Tagesliste des Zeitraums rechts -->
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

<div class="grid items-start gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
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

    <Card class="gap-0 py-0">
        <CardHeader class="border-b py-4">
            <CardTitle class="text-base">Tage im Zeitraum</CardTitle>
            <CardDescription>
                {stats.periodOrders.toLocaleString('de-DE')} Bestellungen ·
                {formatPrice(stats.periodRevenue)}
            </CardDescription>
        </CardHeader>
        <div class="max-h-[26rem] overflow-y-auto">
            <table class="w-full text-sm">
                <thead
                    class="sticky top-0 bg-card text-xs text-muted-foreground"
                >
                    <tr class="border-b">
                        <th class="px-6 py-2 text-left font-medium">Tag</th>
                        <th class="px-2 py-2 text-right font-medium">Best.</th>
                        <th class="px-6 py-2 text-right font-medium">Umsatz</th>
                    </tr>
                </thead>
                <tbody>
                    {#each rows as day (day.date.getTime())}
                        <tr class="border-b last:border-b-0">
                            <td class="px-6 py-2 tabular-nums"
                                >{formatDate(day.date, { withYear: true })}</td
                            >
                            <td class="px-2 py-2 text-right tabular-nums"
                                >{day.orders}</td
                            >
                            <td class="px-6 py-2 text-right tabular-nums"
                                >{formatPrice(day.revenue)}</td
                            >
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </Card>
</div>
