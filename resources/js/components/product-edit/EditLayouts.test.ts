import { render, screen, within } from '@testing-library/svelte';
import { createRawSnippet } from 'svelte';
import type { Component } from 'svelte';
import { describe, expect, it, vi } from 'vitest';
import EditLayoutColumns from '@/components/product-edit/EditLayoutColumns.svelte';
import EditLayoutGallery from '@/components/product-edit/EditLayoutGallery.svelte';
import EditLayoutPreview from '@/components/product-edit/EditLayoutPreview.svelte';
import EditLayoutSections from '@/components/product-edit/EditLayoutSections.svelte';
import EditLayoutTabs from '@/components/product-edit/EditLayoutTabs.svelte';
import type {
    ProductEditFields,
    ProductEditPreview,
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
    'actions',
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

const preview: ProductEditPreview = {
    name: 'Rollator Live',
    description: 'Beschreibung live',
    price: '189.00',
    isAvailable: true,
    manufacturer: { id: 1, name: 'Sunrise' },
    category: { slug: 'mobilitaet', name: 'Mobilität' },
    specs: [{ label: 'Gewicht', value: '7 kg' }],
};

const props = { product, ratings, fields, onsubmit: vi.fn() };

function fieldKeys(container: HTMLElement): string[] {
    return [...container.querySelectorAll('[data-field]')].map(
        (el) => (el as HTMLElement).dataset.field as string,
    );
}

describe('Produktmaske – Layout-Varianten', () => {
    it('A renders the stammdaten fields inside one form, in the original order', () => {
        const { container } = render(EditLayoutTabs as Component<any>, {
            props,
        });

        expect(
            screen.getByRole('tab', { name: 'Stammdaten' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('tab', { name: /Bilder/ })).toHaveTextContent(
            '2',
        );
        expect(
            fieldKeys(container.querySelector('form') as HTMLElement),
        ).toEqual([
            'name',
            'price',
            'category',
            'specialties',
            'manufacturer',
            'description',
            'specs',
            'availability',
            'actions',
        ]);
    });

    it('B places every field exactly once and has the sidebar cards', () => {
        const { container } = render(EditLayoutColumns as Component<any>, {
            props,
        });

        const keys = fieldKeys(container);

        for (const key of FIELD_KEYS.filter((k) => k !== 'actions')) {
            expect(keys.filter((k) => k === key)).toHaveLength(1);
        }

        expect(screen.getByText('Preis & Zuordnung')).toBeInTheDocument();
        expect(screen.getByText('Bilder')).toBeInTheDocument();
    });

    it('C puts each group heading above its content, not beside it', () => {
        const { container } = render(EditLayoutSections as Component<any>, {
            props,
        });

        const sections = [...container.querySelectorAll('section[id]')];
        expect(sections.map((s) => s.id)).toEqual([
            'allgemein',
            'preis-zuordnung',
            'technische-daten',
            'bilder',
            'variationen',
            'bewertungen',
            'verfuegbarkeit',
        ]);

        for (const section of sections) {
            const heading = within(section as HTMLElement).getByRole(
                'heading',
                {
                    level: 2,
                },
            );
            const content = section.children[1];

            // Section is a vertical flex column, heading block precedes the content.
            expect(section.className).toContain('flex-col');
            expect(section.className).not.toContain('grid');
            expect(section.children[0]).toContainElement(heading);
            expect(
                section.children[0].compareDocumentPosition(content) &
                    Node.DOCUMENT_POSITION_FOLLOWING,
            ).toBeTruthy();
        }
    });

    it('C offers a jump navigation with all sections', () => {
        render(EditLayoutSections as Component<any>, { props });

        const nav = screen.getByRole('navigation', { name: 'Abschnitte' });
        expect(within(nav).getAllByRole('button')).toHaveLength(7);
        expect(within(nav).getByText('2/5')).toBeInTheDocument();
    });

    it('D shows the main image and the tabs for details', () => {
        const { container } = render(EditLayoutGallery as Component<any>, {
            props,
        });

        expect(container.querySelector('img[src="/a.jpg"]')).not.toBeNull();
        expect(
            screen.getByRole('tab', { name: 'Beschreibung' }),
        ).toBeInTheDocument();
        expect(fieldKeys(container)).toContain('availability');
        expect(fieldKeys(container)).toContain('description');
    });

    it('E renders the shop card from the live form values', () => {
        render(EditLayoutPreview as Component<any>, {
            props: { ...props, preview },
        });

        expect(
            screen.getByRole('heading', { name: 'Rollator Live' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Sunrise')).toBeInTheDocument();
        expect(
            screen.getByRole('tab', { name: 'Detailseite' }),
        ).toBeInTheDocument();
    });
});
