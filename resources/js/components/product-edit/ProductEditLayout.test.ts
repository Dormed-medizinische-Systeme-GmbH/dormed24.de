import { render, screen } from '@testing-library/svelte';
import { createRawSnippet } from 'svelte';
import type { Component } from 'svelte';
import { describe, expect, it, vi } from 'vitest';
import ProductEditLayout from '@/components/product-edit/ProductEditLayout.svelte';
import type {
    ProductEditFields,
    ProductEditProduct,
    ProductRating,
} from '@/types';

vi.mock('@inertiajs/svelte', async (importOriginal) => ({
    ...(await importOriginal<object>()),
    router: { post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

const FIELD_KEYS = [
    'name',
    'price',
    'category',
    'specialties',
    'manufacturer',
    'description',
    'specs',
    'availability',
] as const;

const fields = Object.fromEntries(
    FIELD_KEYS.map((key) => [
        key,
        createRawSnippet(() => ({
            render: () => `<div data-field="${key}">${key}</div>`,
        })),
    ]),
) as unknown as ProductEditFields;

const product: ProductEditProduct = {
    id: 7,
    name: 'Rollator',
    slug: 'rollator',
    description: 'Leicht',
    technical_specs: [],
    price: '189.00',
    manufacturer_id: 1,
    category_slug: 'mobilitaet',
    specialties: [],
    is_available: true,
    images: [
        { id: 1, path: 'a.jpg', sort_order: 0, url: '/a.jpg' },
        { id: 2, path: 'b.jpg', sort_order: 1, url: '/b.jpg' },
    ],
    variants: [
        {
            id: 1,
            label: 'Standard',
            price: '189.00',
            sort_order: 0,
            is_default: true,
        },
    ],
};

const ratings: ProductRating[] = [
    {
        id: 1,
        stars: 5,
        content: 'Top',
        created_at: '12.09.2026',
        verified: true,
    },
];

function renderLayout() {
    return render(ProductEditLayout as Component<any>, {
        props: { product, ratings, fields, onsubmit: vi.fn() },
    });
}

describe('ProductEditLayout', () => {
    it('shows the main image in a square frame, like in the shop', () => {
        const { container } = renderLayout();

        const frame = container
            .querySelector('img[src="/a.jpg"]')
            ?.closest('div');

        expect(frame?.className).toContain('aspect-square');
        expect(frame?.className).not.toContain('aspect-[4/3]');
    });

    it('puts the core fields in the form on the right, each exactly once', () => {
        const { container } = renderLayout();
        const form = container.querySelector('form') as HTMLElement;

        expect(
            [...form.querySelectorAll('[data-field]')].map(
                (el) => (el as HTMLElement).dataset.field,
            ),
        ).toEqual([
            'name',
            'price',
            'category',
            'manufacturer',
            'specialties',
            'availability',
        ]);
    });

    it('offers description, specs, variants and ratings as tabs with counts', () => {
        renderLayout();

        expect(
            screen.getByRole('tab', { name: 'Beschreibung' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('tab', { name: /Variationen/ }),
        ).toHaveTextContent('1');
        expect(
            screen.getByRole('tab', { name: /Bewertungen/ }),
        ).toHaveTextContent('1');
    });

    it('has no layout switcher anymore', () => {
        renderLayout();

        expect(
            screen.queryByRole('group', { name: /Layout-Variante/ }),
        ).toBeNull();
    });
});
