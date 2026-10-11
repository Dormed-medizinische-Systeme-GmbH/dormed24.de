import { render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import Index from '@/pages/Checkout/Index.svelte';
import type { Cart } from '@/types';

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

describe('Checkout/Index', () => {
    it('disables the checkout button when the cart is empty', () => {
        render(Index, { props: { cart: emptyCart } });

        const button = screen.getByRole('button', { name: 'Zur Kasse' });

        expect(button).toBeDisabled();
        expect(screen.queryByRole('link', { name: 'Zur Kasse' })).toBeNull();
    });
});
