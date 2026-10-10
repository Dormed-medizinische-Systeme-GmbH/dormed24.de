import type { Snippet } from 'svelte';

export type DashboardTotals = { revenue: number; orders: number };

export type DashboardDay = { date: Date; orders: number; revenue: number };

/** Aus den vorhandenen Diagrammdaten des gewählten Zeitraums abgeleitet (nur Anzeige). */
export type DashboardStats = {
    periodRevenue: number;
    periodOrders: number;
    averageOrderValue: number | null;
    bestDay: DashboardDay | null;
    /** Veränderung des Umsatzes zur Vorperiode in Prozent. */
    trendPercent: number | null;
};

/** Gemeinsame Props aller Layout-Varianten; `Dashboard.svelte` besitzt Zustand und Diagramm. */
export type DashboardLayoutProps = {
    totals: DashboardTotals;
    stats: DashboardStats;
    /** Tage des gewählten Zeitraums, älteste zuerst. */
    days: DashboardDay[];
    dateRange: string;
    /** Zeitraum-Auswahl als Popover. */
    rangePopover: Snippet;
    /** Zeitraum-Auswahl als Segment-Buttons. */
    rangeSegments: Snippet;
    chart: Snippet;
    /** Trend-Satz zur Vorperiode (leer, wenn kein Vergleich möglich ist). */
    trend: Snippet;
};
