import { render, screen } from '@testing-library/svelte';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Index from '@/pages/Checkout/Index.svelte';
import type { Cart } from '@/types';

const { pageMock } = vi.hoisted(() => ({
    pageMock: { url: '/checkout', props: {} as Record<string, unknown> },
}));

vi.mock('@inertiajs/svelte', async (importOriginal) => ({
    ...(await importOriginal<object>()),
    page: pageMock,
    router: { on: vi.fn(() => () => {}), patch: vi.fn(), delete: vi.fn() },
}));

// Der Header hängt an Seiten-Props und Router und ist hier nicht Thema.
vi.mock('@/components/ShopHeader.svelte', () => ({ default: () => {} }));

const emptyCart: Cart = {
    items: [],
    count: 0,
    is_empty: true,
    vat_rate: 19,
    shipping_methods: [],
    selected_shipping_method: null,
    subtotal: '0.00',
    shipping_total: '0.00',
    total: '0.00',
    net_total: '0.00',
    vat_amount: '0.00',
    shipping_address: {} as Cart['shipping_address'],
    billing_address: null,
};

const filledCart: Cart = {
    ...emptyCart,
    count: 2,
    is_empty: false,
    items: [
        {
            line_key: '1',
            product_id: 1,
            variant_id: null,
            variant_label: null,
            name: 'Blutdruckmessgerät',
            description: null,
            product_number: '1',
            manufacturer_name: 'Mindray',
            quantity: 2,
            unit_price: '10.00',
            line_total: '20.00',
            image_url: null,
            product_url: '/zubehoer/blutdruckmessgeraet',
            is_available: true,
        },
    ],
    shipping_methods: [
        {
            id: 'standard',
            label: 'Standard',
            description: 'DHL',
            price: '4.90',
            price_cents: 490,
            selected: true,
        },
    ],
    subtotal: '20.00',
    total: '24.90',
};

const VARIANTS = ['A', 'B', 'C', 'D', 'E', 'F'];

describe('Checkout/Index', () => {
    beforeEach(() => {
        pageMock.url = '/checkout';
    });

    it('disables the checkout button when the cart is empty', () => {
        render(Index, { props: { cart: emptyCart } });

        const button = screen.getByRole('button', { name: 'Zur Kasse' });

        expect(button).toBeDisabled();
        expect(screen.queryByRole('link', { name: 'Zur Kasse' })).toBeNull();
    });

    it.each(VARIANTS)(
        'variant %s renders items, shipping and a working checkout link',
        (key) => {
            pageMock.url = `/checkout?variant=${key}`;

            render(Index, { props: { cart: filledCart } });

            expect(
                screen.getByRole('link', { name: 'Blutdruckmessgerät' }),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('radio', { name: /Standard/ }),
            ).toBeChecked();
            expect(
                screen.getByRole('link', { name: 'Zur Kasse' }),
            ).toHaveAttribute(
                'href',
                expect.stringContaining('/checkout/confirm'),
            );
            expect(
                screen.getByRole('group', { name: /Layout-Variante/ }),
            ).toBeInTheDocument();
        },
    );

    it.each(VARIANTS)('variant %s renders the empty state', (key) => {
        pageMock.url = `/checkout?variant=${key}`;

        render(Index, { props: { cart: emptyCart } });

        expect(screen.getByText('Ihr Warenkorb ist leer')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Zur Kasse' }),
        ).toBeDisabled();
    });
});
