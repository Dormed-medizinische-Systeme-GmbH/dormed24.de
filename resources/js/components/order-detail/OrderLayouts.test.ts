import { fireEvent, render, screen } from '@testing-library/svelte';
import type { Component } from 'svelte';
import { describe, expect, it, vi } from 'vitest';
import OrderLayoutCards from '@/components/order-detail/OrderLayoutCards.svelte';
import OrderLayoutPayment from '@/components/order-detail/OrderLayoutPayment.svelte';
import OrderLayoutReceipt from '@/components/order-detail/OrderLayoutReceipt.svelte';
import OrderLayoutSidebar from '@/components/order-detail/OrderLayoutSidebar.svelte';
import OrderLayoutTabs from '@/components/order-detail/OrderLayoutTabs.svelte';
import { ORDER_VARIANTS } from '@/lib/orderDetail';
import type { OrderDetail } from '@/types';

vi.mock('@inertiajs/svelte', () => ({
    page: {
        props: {
            orderStatuses: {
                pending: { label: 'Zahlung ausstehend', tone: 'warning' },
            },
        },
    },
    router: { on: vi.fn(() => () => {}) },
}));

const order: OrderDetail = {
    id: 1234,
    status: 'pending',
    payment_method: 'invoice',
    total_amount: '218.00',
    shipping_amount: '4.90',
    shipping_address: {
        first_name: 'Erika',
        last_name: 'Muster',
        street: 'Hauptstr.',
        house_number: '5',
        zip: '10115',
        city: 'Berlin',
    },
    billing_address: {
        company: 'Muster GmbH',
        first_name: 'Max',
        last_name: 'Buchhaltung',
        street: 'Rechnungsweg',
        house_number: '9',
        zip: '80331',
        city: 'München',
    },
    created_at: '2026-09-12T14:30:00Z',
    updated_at: '2026-09-12T14:30:00Z',
    customer: { id: 3, name: 'Erika Muster', email: 'erika@example.test' },
    items: [
        {
            id: 1,
            product_name: 'Aluminium-Rollator',
            unit_price: '189.00',
            quantity: 1,
            image_url: '/rollator.jpg',
        },
        {
            id: 2,
            product_name: 'Einkaufstasche',
            unit_price: '15.00',
            quantity: 2,
            image_url: null,
        },
    ],
    payments: [
        {
            id: 1,
            paypal_order_id: 'PAYPAL-ABC123',
            status: 'COMPLETED',
            amount: '218.00',
            currency: 'EUR',
            payer_email: 'payer@example.test',
            payer_name: 'Erika Payer',
            created_at: '2026-09-12T14:31:00Z',
        },
    ],
    summary: {
        subtotal: '219.00',
        shipping_total: '4.90',
        vat_rate: 19,
        vat_amount: '35.78',
        total: '223.90',
    },
};

const layouts: [string, Component<any>][] = [
    ['A', OrderLayoutCards],
    ['B', OrderLayoutReceipt],
    ['C', OrderLayoutSidebar],
    ['D', OrderLayoutTabs],
    ['E', OrderLayoutPayment],
];

/** Inhalte, die jede Variante zeigen muss: die Layouts ändern nur die Optik. */
const ALWAYS_VISIBLE = [
    'Bestellung #1234',
    'Zahlung ausstehend',
    'Erika Muster',
    'Aluminium-Rollator',
    'Einkaufstasche',
    // Mengen, Einzel- und Zeilenpreise der Positionen
    '189,00 €*',
    '30,00 €*',
    'Zwischensumme',
    'MwSt. (19 %)',
    'Gesamt',
    'Erika Muster, Hauptstr. 5, 10115 Berlin',
    'Muster GmbH, Max Buchhaltung, Rechnungsweg 9, 80331 München',
];

describe('Bestelldetail – Layout-Varianten', () => {
    it('offers exactly the variants A to E, A first', () => {
        expect(ORDER_VARIANTS.map((variant) => variant.key)).toEqual([
            'A',
            'B',
            'C',
            'D',
            'E',
        ]);
    });

    /** Reiter-Layouts (D) zeigen nur den aktiven Reiter: alle nacheinander öffnen. */
    async function openAllTabs(): Promise<void> {
        for (const tab of screen.queryAllByRole('tab')) {
            await fireEvent.mouseDown(tab);
            await fireEvent.click(tab);
        }
    }

    it.each(layouts)('%s shows the same order facts', async (_key, Layout) => {
        const { container } = render(Layout, { props: { order } });
        await openAllTabs();
        const text = container.textContent ?? '';

        for (const expected of ALWAYS_VISIBLE) {
            expect(text.replace(/\s+/g, ' ')).toContain(expected);
        }

        expect(
            screen.getByRole('link', { name: 'erika@example.test' }),
        ).toHaveAttribute('href', 'mailto:erika@example.test');
        expect(
            container.querySelector('img[src="/rollator.jpg"]'),
        ).not.toBeNull();
    });

    it.each(layouts)('%s lists payments and totals', async (_key, Layout) => {
        const { container } = render(Layout, { props: { order } });
        await openAllTabs();
        const text = (container.textContent ?? '').replace(/\s+/g, ' ');

        expect(text).toContain('Abgeschlossen');
        expect(text).toContain('218,00');

        expect(text).toContain('PAYPAL-ABC123');
        expect(text).toContain('4,90');
    });

    it.each(layouts)(
        '%s handles missing customer and items',
        (_key, Layout) => {
            const { container } = render(Layout, {
                props: {
                    order: {
                        ...order,
                        customer: null,
                        items: [],
                        payments: [],
                        shipping_address: null,
                    },
                },
            });
            const text = container.textContent ?? '';

            expect(text).toContain('Kunde nicht verfügbar');
            expect(text).toContain('Keine Positionen vorhanden');
            expect(text).toContain('—');
        },
    );
});
