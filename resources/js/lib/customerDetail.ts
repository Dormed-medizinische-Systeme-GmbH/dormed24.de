import { formatDate } from '@/lib/date';
import type { CustomerDetail, CustomerOrder } from '@/types';

/** Layout-Varianten der Kundendetail-Ansicht, siehe lib/layoutVariants.ts. */
export const CUSTOMER_VARIANTS = [
    { key: 'A', label: 'Karten (Standard)' },
    { key: 'B', label: 'Profil-Kopf' },
    { key: 'C', label: 'Profil-Spalte' },
    { key: 'D', label: 'Reiter' },
    { key: 'E', label: 'Aktivität' },
] as const;

export const addressTypeLabels: Record<string, string> = {
    shipping: 'Lieferadresse',
    billing: 'Rechnungsadresse',
    both: 'Liefer- & Rechnungsadresse',
};

/** Datum im Projektformat mit Jahr, z. B. „01.05.2026“. */
export function formatDay(iso: string): string {
    return formatDate(iso, { withYear: true });
}

export type CustomerStats = {
    orderCount: number;
    addressCount: number;
    /** Summe aller geladenen Bestellungen, unabhängig vom Status. */
    ordersTotal: number;
    averageOrderValue: number | null;
    lastOrderAt: string | null;
};

/** Aus den vorhandenen Kundendaten abgeleitet (nur Anzeige). */
export function customerStats(customer: CustomerDetail): CustomerStats {
    const ordersTotal = customer.orders.reduce(
        (sum, order) => sum + Number(order.total_amount),
        0,
    );
    const newestFirst = [...customer.orders].sort(
        (a, b) => Date.parse(b.created_at) - Date.parse(a.created_at),
    );

    return {
        orderCount: customer.orders.length,
        addressCount: customer.addresses.length,
        ordersTotal,
        averageOrderValue:
            customer.orders.length > 0
                ? ordersTotal / customer.orders.length
                : null,
        lastOrderAt: newestFirst[0]?.created_at ?? null,
    };
}

export type TimelineEvent =
    | { key: string; at: string; kind: 'registered' }
    | { key: string; at: string; kind: 'verified' }
    | { key: string; at: string; kind: 'order'; order: CustomerOrder };

/** Registrierung, E-Mail-Bestätigung und Bestellungen, neueste zuerst. */
export function customerTimeline(customer: CustomerDetail): TimelineEvent[] {
    const events: TimelineEvent[] = [
        { key: 'registered', at: customer.created_at, kind: 'registered' },
        ...customer.orders.map((order): TimelineEvent => ({
            key: `order-${order.id}`,
            at: order.created_at,
            kind: 'order',
            order,
        })),
    ];

    if (customer.email_verified_at) {
        events.push({
            key: 'verified',
            at: customer.email_verified_at,
            kind: 'verified',
        });
    }

    return events.sort((a, b) => Date.parse(b.at) - Date.parse(a.at));
}
