import { render, screen } from '@testing-library/svelte';
import { describe, expect, it } from 'vitest';
import ProductCard from '@/components/ProductCard.svelte';

type ProductImage = { id: number; url: string; sort_order: number };
type CardProduct = {
    id: number;
    name: string;
    price: string;
    manufacturer: { id: number; name: string } | null;
    images: ProductImage[];
};

function product(overrides: Partial<CardProduct> = {}): CardProduct {
    return {
        id: 5,
        name: 'Blutdruckmessgerät',
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
});
