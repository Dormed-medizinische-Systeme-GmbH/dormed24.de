import { render, screen } from '@testing-library/svelte';
import { createRawSnippet } from 'svelte';
import type { Component } from 'svelte';
import { describe, expect, it } from 'vitest';
import ConfirmLayoutBar from '@/components/checkout-confirm/ConfirmLayoutBar.svelte';
import ConfirmLayoutCollapsible from '@/components/checkout-confirm/ConfirmLayoutCollapsible.svelte';
import ConfirmLayoutStacked from '@/components/checkout-confirm/ConfirmLayoutStacked.svelte';
import ConfirmLayoutStandard from '@/components/checkout-confirm/ConfirmLayoutStandard.svelte';
import ConfirmLayoutSteps from '@/components/checkout-confirm/ConfirmLayoutSteps.svelte';
import ConfirmLayoutTabs from '@/components/checkout-confirm/ConfirmLayoutTabs.svelte';
import { CHECKOUT_CONFIRM_VARIANTS } from '@/lib/checkoutConfirm';
import type { CheckoutConfirmFields } from '@/types';

const KEYS = [
    'terms',
    'shippingAddress',
    'billingAddress',
    'totals',
    'pay',
    'shippingSummary',
    'billingSummary',
    'note',
] as const;

const fields = Object.fromEntries(
    KEYS.map((key) => [
        key,
        createRawSnippet(() => ({
            render: () => `<div data-field="${key}">${key}</div>`,
        })),
    ]),
) as unknown as CheckoutConfirmFields;

const layouts: [string, Component<any>][] = [
    ['A', ConfirmLayoutStandard],
    ['B', ConfirmLayoutStacked],
    ['D', ConfirmLayoutSteps],
    ['E', ConfirmLayoutBar],
    ['F', ConfirmLayoutCollapsible],
];

const MAIN = ['terms', 'shippingAddress', 'billingAddress', 'totals', 'pay'];

function keys(container: HTMLElement): string[] {
    return [...container.querySelectorAll('[data-field]')].map(
        (el) => (el as HTMLElement).dataset.field as string,
    );
}

describe('Bestellung abschließen – Layout-Varianten', () => {
    it('offers exactly the variants A to F, A first', () => {
        expect(CHECKOUT_CONFIRM_VARIANTS.map((variant) => variant.key)).toEqual(
            ['A', 'B', 'C', 'D', 'E', 'F'],
        );
    });

    it.each(layouts)(
        '%s places terms, both addresses, totals and payment exactly once',
        (_key, Layout) => {
            const { container } = render(Layout, { props: { fields } });

            for (const key of MAIN) {
                expect(keys(container).filter((k) => k === key)).toHaveLength(
                    1,
                );
            }
        },
    );

    it.each(layouts)('%s shows the price note exactly once', (_key, Layout) => {
        const { container } = render(Layout, { props: { fields } });

        expect(keys(container).filter((k) => k === 'note')).toHaveLength(1);
    });

    it('C splits addresses and payment into two tabs', () => {
        const { container } = render(ConfirmLayoutTabs as Component<any>, {
            props: { fields },
        });

        const panels = [
            ...container.querySelectorAll<HTMLElement>('[role=tabpanel]'),
        ];

        expect(panels.map((panel) => keys(panel))).toEqual([
            ['shippingAddress', 'billingAddress'],
            ['terms', 'totals', 'pay', 'note'],
        ]);
        expect(
            screen.getByRole('tab', { name: /Prüfen & bezahlen/ }),
        ).toBeInTheDocument();
    });

    it('D numbers the four steps in order', () => {
        const { container } = render(ConfirmLayoutSteps as Component<any>, {
            props: { fields },
        });

        const steps = [...container.querySelectorAll('ol > li')];

        expect(steps).toHaveLength(4);
        expect(steps.map((step) => keys(step as HTMLElement))).toEqual([
            ['shippingAddress'],
            ['billingAddress'],
            ['terms'],
            ['pay'],
        ]);
    });

    it('E renders the payment methods side by side', () => {
        const spy: boolean[] = [];
        const probe = {
            ...fields,
            pay: createRawSnippet((sideBySide: () => boolean | undefined) => {
                spy.push(Boolean(sideBySide()));

                return { render: () => '<div data-field="pay">pay</div>' };
            }),
        } as unknown as CheckoutConfirmFields;

        render(ConfirmLayoutBar as Component<any>, {
            props: { fields: probe },
        });

        expect(spy).toEqual([true]);
    });

    it('F summarizes the addresses in the collapsed headers', () => {
        const { container } = render(
            ConfirmLayoutCollapsible as Component<any>,
            { props: { fields } },
        );

        const details = [...container.querySelectorAll('details')];

        expect(details).toHaveLength(2);
        expect(keys(details[0])).toEqual([
            'shippingSummary',
            'shippingAddress',
        ]);
        expect(keys(details[1])).toEqual(['billingSummary', 'billingAddress']);
    });
});
