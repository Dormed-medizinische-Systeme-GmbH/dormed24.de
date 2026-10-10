import { render, screen, within } from '@testing-library/svelte';
import { createRawSnippet } from 'svelte';
import type { Component } from 'svelte';
import { describe, expect, it } from 'vitest';
import DashboardLayoutCards from '@/components/dashboard/DashboardLayoutCards.svelte';
import DashboardLayoutDays from '@/components/dashboard/DashboardLayoutDays.svelte';
import DashboardLayoutHero from '@/components/dashboard/DashboardLayoutHero.svelte';
import DashboardLayoutSegments from '@/components/dashboard/DashboardLayoutSegments.svelte';
import DashboardLayoutSidebar from '@/components/dashboard/DashboardLayoutSidebar.svelte';
import { DASHBOARD_VARIANTS } from '@/lib/dashboard';
import type { DashboardLayoutProps } from '@/types';

const slot = (id: string) =>
    createRawSnippet(() => ({
        render: () => `<div data-slot-id="${id}">${id}</div>`,
    }));

const props: DashboardLayoutProps = {
    totals: { revenue: 12345.6, orders: 1234 },
    stats: {
        periodRevenue: 500,
        periodOrders: 5,
        averageOrderValue: 100,
        bestDay: null,
        trendPercent: 12.5,
    },
    days: [
        { date: new Date('2026-09-01T00:00:00Z'), orders: 2, revenue: 200 },
        { date: new Date('2026-09-02T00:00:00Z'), orders: 3, revenue: 300 },
    ],
    dateRange: '01.09. – 02.09.',
    rangePopover: slot('range-popover'),
    rangeSegments: slot('range-segments'),
    chart: slot('chart'),
    trend: slot('trend'),
};

const layouts: [string, Component<any>][] = [
    ['A', DashboardLayoutCards],
    ['B', DashboardLayoutHero],
    ['C', DashboardLayoutSidebar],
    ['D', DashboardLayoutSegments],
    ['E', DashboardLayoutDays],
];

describe('Dashboard – Layout-Varianten', () => {
    it('offers exactly the variants A to E, A first', () => {
        expect(DASHBOARD_VARIANTS.map((variant) => variant.key)).toEqual([
            'A',
            'B',
            'C',
            'D',
            'E',
        ]);
    });

    it.each(layouts)('%s shows totals, chart and trend', (_key, Layout) => {
        const { container } = render(Layout, { props });
        const text = (container.textContent ?? '').replace(/\s/g, ' ');

        expect(text).toContain('12.345,60');
        expect(text).toContain('1.234');
        expect(
            container.querySelectorAll('[data-slot-id="chart"]'),
        ).toHaveLength(1);
        expect(
            container.querySelectorAll('[data-slot-id="trend"]'),
        ).toHaveLength(1);
    });

    it.each(layouts)('%s offers exactly one range picker', (_key, Layout) => {
        const { container } = render(Layout, { props });

        expect(
            container.querySelectorAll(
                '[data-slot-id="range-popover"], [data-slot-id="range-segments"]',
            ),
        ).toHaveLength(1);
    });

    it.each(layouts)(
        '%s hides the trend without a comparison',
        (_key, Layout) => {
            const { container } = render(Layout, {
                props: {
                    ...props,
                    stats: { ...props.stats, trendPercent: null },
                },
            });

            expect(
                container.querySelector('[data-slot-id="trend"]'),
            ).toBeNull();
        },
    );

    it('E lists the days newest first with orders and revenue', () => {
        render(DashboardLayoutDays as Component<any>, { props });

        const rows = within(screen.getByRole('table')).getAllByRole('row');

        // Kopfzeile + 2 Tage; neuester Tag (02.09.) zuerst
        expect(rows).toHaveLength(3);
        expect(rows[1]).toHaveTextContent('02.09.2026');
        expect(rows[1]).toHaveTextContent('300,00');
        expect(rows[2]).toHaveTextContent('01.09.2026');
    });
});
