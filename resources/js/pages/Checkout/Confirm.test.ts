import { render, screen } from '@testing-library/svelte';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Confirm from '@/pages/Checkout/Confirm.svelte';
import type { Cart } from '@/types';

const { pageMock } = vi.hoisted(() => ({
    pageMock: {
        url: '/checkout/confirm',
        props: { auth: { user: { id: 1 } } } as Record<string, unknown>,
    },
}));

vi.mock('@inertiajs/svelte', async (importOriginal) => ({
    ...(await importOriginal<object>()),
    page: pageMock,
    router: { on: vi.fn(() => () => {}), patch: vi.fn(), post: vi.fn() },
}));

vi.mock('@/components/ShopHeader.svelte', () => ({ default: () => {} }));
vi.mock('@/components/PayPalButtons.svelte', () => ({ default: () => {} }));

const address = {
    company: '',
    salutation: '',
    first_name: 'Erika',
    last_name: 'Mustermann',
    street: 'Musterstraße',
    house_number: '1',
    address_line2: '',
    zip: '44135',
    city: 'Dortmund',
    country: 'DE',
    phone: '',
};

const cart: Cart = {
    items: [],
    count: 1,
    is_empty: false,
    vat_rate: 19,
    shipping_methods: [],
    selected_shipping_method: null,
    subtotal: '20.00',
    shipping_total: '4.90',
    total: '24.90',
    net_total: '20.92',
    vat_amount: '3.98',
    shipping_address: address,
    billing_address: null,
};

const VARIANTS = ['A', 'B', 'C', 'D', 'E', 'F'];

describe('Checkout/Confirm', () => {
    beforeEach(() => {
        pageMock.url = '/checkout/confirm';
        pageMock.props.auth = { user: { id: 1 } };
    });

    it.each(VARIANTS)(
        'variant %s offers terms, shipping address, summary and invoice purchase',
        (key) => {
            pageMock.url = `/checkout/confirm?variant=${key}`;

            render(Confirm, { props: { cart, paypal_client_id: 'x' } });

            // In Variante C liegt „Prüfen & bezahlen“ im zweiten Reiter.
            if (key === 'C') {
                expect(screen.getByText('Lieferadresse')).toBeInTheDocument();

                return;
            }

            expect(screen.getAllByText('Lieferadresse').length).toBeGreaterThan(
                0,
            );
            expect(
                screen.getByText('AGB und Widerrufsbelehrung'),
            ).toBeInTheDocument();
            expect(screen.getByText('Zusammenfassung')).toBeInTheDocument();
            expect(
                screen.getByRole('button', { name: 'Kaufen auf Rechnung' }),
            ).toBeDisabled();
        },
    );

    it.each(VARIANTS.filter((key) => key !== 'C'))(
        'variant %s asks guests to log in instead of offering payment',
        (key) => {
            pageMock.url = `/checkout/confirm?variant=${key}`;
            pageMock.props.auth = { user: null };

            render(Confirm, { props: { cart } });

            expect(
                screen.getByRole('link', { name: 'Zum Login' }),
            ).toBeInTheDocument();
            expect(
                screen.queryByRole('button', { name: 'Kaufen auf Rechnung' }),
            ).toBeNull();
        },
    );

    it('F shows the saved address in the collapsed header', () => {
        pageMock.url = '/checkout/confirm?variant=F';

        render(Confirm, { props: { cart } });

        expect(
            screen.getByText(
                'Erika Mustermann, Musterstraße 1, 44135 Dortmund',
            ),
        ).toBeInTheDocument();
        // Kurzfassung im Kopf und Text im aufgeklappten Bereich.
        expect(
            screen.getAllByText('Identisch mit Lieferadresse.'),
        ).toHaveLength(2);
    });
});
