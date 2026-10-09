import { render, screen } from '@testing-library/svelte';
import { describe, expect, it } from 'vitest';
import ProductCard from '@/components/ProductCard.svelte';

type ProductImage = { id: number; url: string; sort_order: number };
type CardProduct = {
    id: number;
    name: string;
    description: string | null;
    price: string;
    old_price?: string | null;
    is_available?: boolean;
    manufacturer: { id: number; name: string } | null;
    images: ProductImage[];
    ratings_avg_stars?: number | string | null;
    ratings_count?: number;
};

function product(overrides: Partial<CardProduct> = {}): CardProduct {
    return {
        id: 5,
        name: 'Blutdruckmessgerät',
        description: 'Oberarm-Messgerät mit Speicher für zwei Personen',
        price: '49.90',
        manufacturer: { id: 1, name: 'Beurer' },
        images: [
            { id: 1, url: '/storage/products/5/cover.jpg', sort_order: 0 },
        ],
        ...overrides,
    };
}

describe('ProductCard', () => {
    it('renders the product name, formatted price and manufacturer', () => {
        render(ProductCard, { props: { product: product() } });

        expect(screen.getByText('Blutdruckmessgerät')).toBeInTheDocument();
        expect(screen.getByText('49,90 €*')).toBeInTheDocument();
        expect(screen.getByText('Beurer')).toBeInTheDocument();
    });

    it('links to the product detail page', () => {
        render(ProductCard, { props: { product: product() } });

        const link = screen.getByRole('link') as HTMLAnchorElement;

        expect(new URL(link.href).pathname).toBe('/products/5');
    });

    it('renders the cover image with the product name as alt text', () => {
        render(ProductCard, { props: { product: product() } });

        expect(
            screen.getByRole('img', { name: 'Blutdruckmessgerät' }),
        ).toHaveAttribute('src', '/storage/products/5/cover.jpg');
    });

    it('renders a placeholder instead of an image when there are none', () => {
        render(ProductCard, { props: { product: product({ images: [] }) } });

        expect(screen.queryByRole('img')).not.toBeInTheDocument();
    });

    it('omits the manufacturer line when there is none', () => {
        render(ProductCard, {
            props: { product: product({ manufacturer: null }) },
        });

        expect(screen.queryByText('Beurer')).not.toBeInTheDocument();
    });

    it('renders the description', () => {
        render(ProductCard, { props: { product: product() } });

        expect(
            screen.getByText(
                'Oberarm-Messgerät mit Speicher für zwei Personen',
            ),
        ).toBeInTheDocument();
    });

    it('omits the description when there is none', () => {
        render(ProductCard, {
            props: { product: product({ description: null }) },
        });

        expect(
            screen.queryByText(
                'Oberarm-Messgerät mit Speicher für zwei Personen',
            ),
        ).not.toBeInTheDocument();
    });

    it('shows the rating with the average and the review count', () => {
        render(ProductCard, {
            props: {
                product: product({
                    ratings_avg_stars: '4.5',
                    ratings_count: 12,
                }),
            },
        });

        expect(
            screen.getByRole('img', { name: 'Bewertung: 4,5 von 5 Sternen' }),
        ).toBeInTheDocument();
        expect(screen.getByText('12 Bewertungen')).toBeInTheDocument();
    });

    it('omits the rating when there are no reviews', () => {
        render(ProductCard, {
            props: { product: product({ ratings_count: 0 }) },
        });

        expect(screen.queryByText(/Bewertungen/)).not.toBeInTheDocument();
    });

    it('shows the sale badge and the struck-through old price when there is an old price', () => {
        render(ProductCard, {
            props: { product: product({ old_price: '59.90' }) },
        });

        expect(screen.getByText('Angebot')).toBeInTheDocument();
        expect(screen.getByText('59,90 €')).toHaveClass('line-through');
        expect(screen.getByText('49,90 €*')).toBeInTheDocument();
    });

    it('omits the sale badge and the old price when there is no old price', () => {
        const { container } = render(ProductCard, {
            props: { product: product({ old_price: null }) },
        });

        expect(screen.queryByText('Angebot')).not.toBeInTheDocument();
        expect(container.querySelector('.line-through')).toBeNull();
    });

    it('shows the product action when the product is available', () => {
        render(ProductCard, { props: { product: product() } });

        expect(screen.getByText('Zum Produkt')).toBeInTheDocument();
        expect(screen.queryByText('Nicht verfügbar')).not.toBeInTheDocument();
    });

    it('shows the disabled state when the product is not available', () => {
        render(ProductCard, {
            props: { product: product({ is_available: false }) },
        });

        expect(screen.getByText('Nicht verfügbar')).toBeInTheDocument();
        expect(screen.queryByText('Zum Produkt')).not.toBeInTheDocument();
    });
});
