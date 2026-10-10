import { render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import Edit from '@/pages/Admin/Products/Edit.svelte';

const putMock = vi.fn();

vi.mock('@inertiajs/svelte', async (importOriginal) => ({
    ...(await importOriginal<object>()),
    useForm: (initial: Record<string, unknown>) => ({
        ...initial,
        errors: {},
        processing: false,
        isDirty: false,
        put: putMock,
    }),
    setLayoutProps: vi.fn(),
    router: {
        on: vi.fn(() => () => {}),
        post: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
}));

const props = {
    product: {
        id: 34,
        name: 'Tragbares Ultraschallsystem MU7',
        slug: 'tragbares-ultraschallsystem-mu7',
        description: 'Beschreibung',
        technical_specs: [],
        price: '4390.00',
        manufacturer_id: 1,
        category_slug: 'ultraschall',
        specialties: [],
        is_available: true,
        images: [{ id: 1, path: 'a.jpg', sort_order: 0, url: '/a.jpg' }],
        variants: [],
    },
    manufacturers: [{ id: 1, name: 'Mindray' }],
    categories: [{ slug: 'ultraschall', name: 'Ultraschallsysteme' }],
    specialtyCategories: [],
    specialties: [],
    ratings: [],
};

describe('Admin/Products/Edit', () => {
    it('shows the product name as a heading only, not as an input', () => {
        render(Edit, { props });

        expect(
            screen.getByRole('heading', {
                level: 2,
                name: 'Tragbares Ultraschallsystem MU7',
            }),
        ).toBeInTheDocument();
        expect(screen.queryByLabelText('Name')).toBeNull();
        expect(
            screen.queryByDisplayValue('Tragbares Ultraschallsystem MU7'),
        ).toBeNull();
    });

    it('shows availability and article id next to the name', () => {
        render(Edit, { props });

        expect(screen.getByText('Verfügbar')).toBeInTheDocument();
        expect(screen.getByText('Artikel-ID 34')).toBeInTheDocument();
    });

    it('offers a large price input with the muted € sign inside the field', () => {
        render(Edit, { props });

        const price = screen.getByLabelText('Preis');
        const euro = screen.getByText('€');

        expect(price).toHaveValue(4390);
        expect(price.className).toContain('h-12');
        expect(price.parentElement).toContainElement(euro);
        expect(euro.className).toContain('text-muted-foreground');
        expect(euro).toHaveAttribute('aria-hidden', 'true');
    });
});
