import { render } from '@testing-library/svelte';
import { createRawSnippet } from 'svelte';
import type { Component } from 'svelte';
import { describe, expect, it } from 'vitest';
import CartLayoutBar from '@/components/cart-page/CartLayoutBar.svelte';
import CartLayoutReceipt from '@/components/cart-page/CartLayoutReceipt.svelte';
import CartLayoutRows from '@/components/cart-page/CartLayoutRows.svelte';
import CartLayoutShippingFirst from '@/components/cart-page/CartLayoutShippingFirst.svelte';
import CartLayoutStacked from '@/components/cart-page/CartLayoutStacked.svelte';
import CartLayoutStandard from '@/components/cart-page/CartLayoutStandard.svelte';
import { CART_VARIANTS } from '@/lib/cartPage';
import type { CartPageFields } from '@/types';

const KEYS = [
    'empty',
    'items',
    'shipping',
    'totals',
    'checkout',
    'totalLine',
    'note',
] as const;

const fields = Object.fromEntries(
    KEYS.map((key) => [
        key,
        createRawSnippet(() => ({
            render: () => `<div data-field="${key}">${key}</div>`,
        })),
    ]),
) as unknown as CartPageFields;

const layouts: [string, Component<any>][] = [
    ['A', CartLayoutStandard],
    ['B', CartLayoutRows],
    ['C', CartLayoutStacked],
    ['D', CartLayoutShippingFirst],
    ['E', CartLayoutBar],
    ['F', CartLayoutReceipt],
];

function keys(container: HTMLElement): string[] {
    return [...container.querySelectorAll('[data-field]')].map(
        (el) => (el as HTMLElement).dataset.field as string,
    );
}

describe('Warenkorb – Layout-Varianten', () => {
    it('offers exactly the variants A to F, A first', () => {
        expect(CART_VARIANTS.map((variant) => variant.key)).toEqual([
            'A',
            'B',
            'C',
            'D',
            'E',
            'F',
        ]);
    });

    it.each(layouts)(
        '%s places items, shipping, totals and checkout exactly once',
        (_key, Layout) => {
            const { container } = render(Layout, {
                props: { fields, isEmpty: false },
            });

            for (const key of ['items', 'shipping', 'totals', 'checkout']) {
                expect(keys(container).filter((k) => k === key)).toHaveLength(
                    1,
                );
            }

            expect(keys(container)).not.toContain('empty');
        },
    );

    it.each(layouts)(
        '%s shows the empty state instead of the items for an empty cart',
        (_key, Layout) => {
            const { container } = render(Layout, {
                props: { fields, isEmpty: true },
            });

            expect(keys(container).filter((k) => k === 'empty')).toHaveLength(
                1,
            );
            expect(keys(container)).not.toContain('items');
            // Die Kasse bleibt sichtbar (der Container deaktiviert sie).
            expect(keys(container)).toContain('checkout');
        },
    );

    it.each(layouts)('%s shows the price note exactly once', (_key, Layout) => {
        const { container } = render(Layout, {
            props: { fields, isEmpty: false },
        });

        expect(keys(container).filter((k) => k === 'note')).toHaveLength(1);
    });
});
