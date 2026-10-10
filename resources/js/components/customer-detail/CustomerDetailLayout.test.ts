import { render, screen } from '@testing-library/svelte';
import type { Component } from 'svelte';
import { describe, expect, it, vi } from 'vitest';
import CustomerDetailLayout from '@/components/customer-detail/CustomerDetailLayout.svelte';
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

const norm = (text: string | null) => (text ?? '').replace(/\s+/g, ' ');

function renderLayout(overrides: Partial<CustomerDetail> = {}) {
    return render(CustomerDetailLayout as Component<any>, {
        props: { customer: { ...customer, ...overrides } },
    });
}

describe('CustomerDetailLayout', () => {
    it('shows name and mail once, in the profile card', () => {
        renderLayout();

        expect(
            screen.getByRole('heading', { level: 1, name: 'Erika Muster' }),
        ).toBeInTheDocument();
        expect(
            screen.getAllByRole('link', { name: 'erika@example.test' }),
        ).toHaveLength(1);
        expect(
            screen.getByRole('link', { name: 'erika@example.test' }),
        ).toHaveAttribute('href', 'mailto:erika@example.test');
    });

    it('puts the profile card above the addresses', () => {
        renderLayout();

        const name = screen.getByRole('heading', { level: 1 });
        const addresses = screen.getByRole('heading', { name: /^Adressen/ });

        expect(
            name.compareDocumentPosition(addresses) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
    });

    it('lists verification, registration and counts in the profile card', () => {
        const { container } = renderLayout();
        const card = container.querySelector(
            '[data-slot="card"]',
        ) as HTMLElement;
        const text = norm(card.textContent);

        expect(text).toContain('E-Mail verifiziert');
        expect(text).toContain('02.01.2026');
        expect(text).toContain('Registriert');
        expect(text).toContain('01.01.2026');
        expect(text).toContain('Bestellungen 2');
        expect(text).toContain('Adressen 2');
    });

    it('shows "Nein" for an unverified email', () => {
        const { container } = renderLayout({ email_verified_at: null });
        const card = container.querySelector(
            '[data-slot="card"]',
        ) as HTMLElement;

        expect(norm(card.textContent)).toContain('E-Mail verifiziert Nein');
    });

    it('shows all addresses', () => {
        const { container } = renderLayout();
        const text = norm(container.textContent);

        for (const expected of [
            'Muster GmbH',
            'Hauptstr. 1',
            'Nebenweg 2',
            '10115 Berlin',
            '030 123456',
            'Standard',
        ]) {
            expect(text).toContain(expected);
        }
    });

    it('lists the orders with status, items and total, newest first', () => {
        const { container } = renderLayout();
        const text = norm(container.textContent);

        expect(text).toContain('Bezahlt');
        expect(text).toContain('Zahlung ausstehend');
        expect(text).toContain('Aluminium-Rollator');
        expect(text).toContain('2 × Einkaufstasche');
        expect(text).toContain('189,00');
        expect(text).toContain('30,00');
        expect(text.indexOf('Bestellung #11')).toBeLessThan(
            text.indexOf('Bestellung #10'),
        );
    });

    it('puts registration and verification into the timeline', () => {
        const { container } = renderLayout();
        const text = norm(container.textContent);

        expect(text).toContain('Kunde registriert');
        expect(text).toContain('E-Mail-Adresse bestätigt');
        expect(text.indexOf('Bestellung #10')).toBeLessThan(
            text.indexOf('E-Mail-Adresse bestätigt'),
        );
    });

    it('handles a customer without orders and addresses', () => {
        const { container } = renderLayout({
            email_verified_at: null,
            orders: [],
            addresses: [],
        });
        const text = norm(container.textContent);

        expect(text).toContain('Erika Muster');
        expect(text).toContain('Keine Adressen hinterlegt');
        expect(text).toContain('Kunde registriert');
        expect(text).not.toContain('E-Mail-Adresse bestätigt');
    });
});
