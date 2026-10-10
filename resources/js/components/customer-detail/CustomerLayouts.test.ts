import { fireEvent, render, screen } from '@testing-library/svelte';
import type { Component } from 'svelte';
import { describe, expect, it, vi } from 'vitest';
import CustomerLayoutCards from '@/components/customer-detail/CustomerLayoutCards.svelte';
import CustomerLayoutProfile from '@/components/customer-detail/CustomerLayoutProfile.svelte';
import CustomerLayoutSidebar from '@/components/customer-detail/CustomerLayoutSidebar.svelte';
import CustomerLayoutTabs from '@/components/customer-detail/CustomerLayoutTabs.svelte';
import CustomerLayoutTimeline from '@/components/customer-detail/CustomerLayoutTimeline.svelte';
import type { CustomerDetail } from '@/types';

vi.mock('@inertiajs/svelte', () => ({
    page: {
        props: {
            orderStatuses: {
                paid: { label: 'Bezahlt', tone: 'success' },
                pending: { label: 'Zahlung ausstehend', tone: 'warning' },
            },
        },
    },
    router: { on: vi.fn(() => () => {}) },
}));

const address = (id: number, type: string, isDefault = false) => ({
    id,
    type,
    company: id === 1 ? 'Muster GmbH' : null,
    salutation: null,
    first_name: 'Erika',
    last_name: 'Muster',
    street: id === 1 ? 'Hauptstr.' : 'Nebenweg',
    house_number: String(id),
    address_line2: null,
    zip: '10115',
    city: 'Berlin',
    country: 'DE',
    phone: id === 1 ? '030 123456' : null,
    is_default: isDefault,
});

const customer: CustomerDetail = {
    id: 1,
    name: 'Erika Muster',
    email: 'erika@example.test',
    email_verified_at: '2026-01-02T10:00:00Z',
    created_at: '2026-01-01T10:00:00Z',
    addresses: [address(1, 'both', true), address(2, 'shipping')],
    orders: [
        {
            id: 10,
            status: 'paid',
            total_amount: '189.00',
            created_at: '2026-03-01T10:00:00Z',
            items: [
                {
                    product_name: 'Aluminium-Rollator',
                    unit_price: '189.00',
                    quantity: 1,
                },
            ],
        },
        {
            id: 11,
            status: 'pending',
            total_amount: '30.00',
            created_at: '2026-05-01T10:00:00Z',
            items: [
                {
                    product_name: 'Einkaufstasche',
                    unit_price: '15.00',
                    quantity: 2,
                },
            ],
        },
    ],
};

const layouts: [string, Component<any>][] = [
    ['A', CustomerLayoutCards],
    ['B', CustomerLayoutProfile],
    ['C', CustomerLayoutSidebar],
    ['D', CustomerLayoutTabs],
    ['E', CustomerLayoutTimeline],
];

/** Reiter-Layouts (D) zeigen nur den aktiven Reiter: alle nacheinander öffnen. */
async function openAllTabs(): Promise<void> {
    for (const tab of screen.queryAllByRole('tab')) {
        await fireEvent.mouseDown(tab);
        await fireEvent.click(tab);
    }
}

const norm = (text: string | null) => (text ?? '').replace(/\s+/g, ' ');

describe('Kundendetail – Layout-Varianten', () => {
    it.each(layouts)(
        '%s shows the same customer facts',
        async (_key, Layout) => {
            const { container } = render(Layout, { props: { customer } });
            await openAllTabs();
            const text = norm(container.textContent);

            for (const expected of [
                'Erika Muster',
                'erika@example.test',
                'Muster GmbH',
                'Hauptstr. 1',
                'Nebenweg 2',
                '10115 Berlin',
                '030 123456',
                'Standard',
            ]) {
                expect(text).toContain(expected);
            }

            expect(
                screen.getAllByRole('link', { name: 'erika@example.test' })[0],
            ).toHaveAttribute('href', 'mailto:erika@example.test');
        },
    );

    it.each(layouts)(
        '%s lists every order with status and total',
        async (key, Layout) => {
            const { container } = render(Layout, { props: { customer } });
            await openAllTabs();
            const text = norm(container.textContent);

            expect(text).toContain('#10');
            expect(text).toContain('#11');
            expect(text).toContain('Bezahlt');
            expect(text).toContain('Zahlung ausstehend');
            expect(text).toContain('189,00');
            expect(text).toContain('30,00');

            // Positionen sind in C (aufklappbar) und E (Zeitachse) ausgeschrieben
            if (key === 'C' || key === 'E') {
                expect(text).toContain('Aluminium-Rollator');
                expect(text).toContain('Einkaufstasche');
            }
        },
    );

    it.each(layouts)(
        '%s handles a customer without orders and addresses',
        async (_key, Layout) => {
            const { container } = render(Layout, {
                props: {
                    customer: {
                        ...customer,
                        email_verified_at: null,
                        orders: [],
                        addresses: [],
                    },
                },
            });
            await openAllTabs();
            const text = norm(container.textContent);

            expect(text).toContain('Erika Muster');
            expect(text).not.toContain('Hauptstr.');
        },
    );

    it('B derives statistics from the orders', () => {
        const { container } = render(CustomerLayoutProfile as Component<any>, {
            props: { customer },
        });
        const text = norm(container.textContent);

        expect(text).toContain('219,00'); // Summe
        expect(text).toContain('109,50'); // Ø
        expect(text).toContain('01.05.2026'); // letzte Bestellung
    });

    it('E shows registration, verification and orders newest first', () => {
        const { container } = render(CustomerLayoutTimeline as Component<any>, {
            props: { customer },
        });
        const text = norm(container.textContent);

        expect(text).toContain('Kunde registriert');
        expect(text).toContain('E-Mail-Adresse bestätigt');
        expect(text.indexOf('Bestellung #11')).toBeLessThan(
            text.indexOf('Bestellung #10'),
        );
        expect(text.indexOf('Bestellung #10')).toBeLessThan(
            text.indexOf('E-Mail-Adresse bestätigt'),
        );
    });
});
