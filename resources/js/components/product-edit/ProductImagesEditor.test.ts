import { fireEvent, render, screen } from '@testing-library/svelte';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ProductImagesEditor from '@/components/product-edit/ProductImagesEditor.svelte';
import type { ProductImage } from '@/types';

const { postMock } = vi.hoisted(() => ({ postMock: vi.fn() }));

vi.mock('@inertiajs/svelte', () => ({
    router: { post: postMock, delete: vi.fn(), patch: vi.fn() },
}));

const image = (id: number): ProductImage => ({
    id,
    path: `${id}.jpg`,
    sort_order: id - 1,
    url: `/${id}.jpg`,
});

function renderEditor(images: ProductImage[]) {
    return render(ProductImagesEditor, { props: { productId: 7, images } });
}

describe('ProductImagesEditor', () => {
    beforeEach(() => {
        postMock.mockReset();
    });

    it('shows the images and exactly one free "+" card', () => {
        renderEditor([image(1), image(2)]);

        expect(screen.getAllByRole('listitem')).toHaveLength(2);
        expect(screen.getAllByText('Bild hinzufügen')).toHaveLength(1);
        expect(screen.getByText(/2\/5 Bilder/)).toBeInTheDocument();
    });

    it('shows no "+" card at five images', () => {
        renderEditor([1, 2, 3, 4, 5].map(image));

        expect(screen.queryByText('Bild hinzufügen')).toBeNull();
        expect(screen.getByText(/5\/5 Bilder/)).toBeInTheDocument();
    });

    it('shows one "+" card even without any image', () => {
        renderEditor([]);

        expect(screen.getAllByText('Bild hinzufügen')).toHaveLength(1);
        expect(screen.queryAllByRole('listitem')).toHaveLength(0);
    });

    it('marks only the first image as main image', () => {
        renderEditor([image(1), image(2)]);

        expect(screen.getAllByText('Hauptbild')).toHaveLength(1);
    });

    it('uploads the chosen file from a free slot', async () => {
        const { container } = renderEditor([image(1)]);
        const input = container.querySelector(
            'input[type="file"]',
        ) as HTMLInputElement;
        const file = new File(['x'], 'neu.png', { type: 'image/png' });

        await fireEvent.change(input, { target: { files: [file] } });

        expect(postMock).toHaveBeenCalledTimes(1);
        expect(postMock.mock.calls[0][0]).toBe('/admin/products/7/images');
        expect((postMock.mock.calls[0][1] as FormData).get('image')).toBe(file);
    });
});
