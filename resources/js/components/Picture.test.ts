import { render } from '@testing-library/svelte';
import { describe, expect, it } from 'vitest';
import Picture from '@/components/Picture.svelte';

function sources(container: HTMLElement) {
    return [...container.querySelectorAll('source')].map((source) => ({
        type: source.getAttribute('type'),
        srcset: source.getAttribute('srcset'),
    }));
}

describe('Picture', () => {
    it('rendert AVIF vor WebP und den WebP-Fallback als img', () => {
        const { container } = render(Picture, {
            src: '/storage/products/7/abc.webp',
            alt: 'Produktname',
            width: 800,
            height: 600,
        });

        expect(sources(container)).toEqual([
            { type: 'image/avif', srcset: '/storage/products/7/abc.avif' },
            { type: 'image/webp', srcset: '/storage/products/7/abc.webp' },
        ]);

        const img = container.querySelector('picture > img')!;
        expect(img.getAttribute('src')).toBe('/storage/products/7/abc.webp');
        expect(img.getAttribute('alt')).toBe('Produktname');
        expect(img.getAttribute('width')).toBe('800');
        expect(img.getAttribute('height')).toBe('600');
        expect(img.getAttribute('loading')).toBe('lazy');
        expect(img.getAttribute('decoding')).toBe('async');
    });

    it('lässt Maße weg, wenn sie nicht bekannt sind', () => {
        const { container } = render(Picture, {
            src: '/storage/a.webp',
            alt: '',
            width: null,
            height: null,
        });
        const img = container.querySelector('img')!;

        expect(img.hasAttribute('width')).toBe(false);
        expect(img.hasAttribute('height')).toBe(false);
    });

    it('liefert Altbestand (kein WebP) als schlichtes img ohne Sources aus', () => {
        const { container } = render(Picture, {
            src: '/storage/products/7/alt.jpg',
            alt: 'Alt',
        });

        expect(sources(container)).toEqual([]);
        expect(container.querySelector('img')!.getAttribute('src')).toBe(
            '/storage/products/7/alt.jpg',
        );
    });

    it('setzt Klassen auf das img und unterstützt eager/fetchpriority', () => {
        const { container } = render(Picture, {
            src: '/storage/a.webp',
            alt: 'x',
            class: 'size-full object-contain',
            loading: 'eager',
            fetchpriority: 'high',
        });
        const img = container.querySelector('img')!;

        expect(img.className).toBe('size-full object-contain');
        expect(img.getAttribute('loading')).toBe('eager');
        expect(img.getAttribute('fetchpriority')).toBe('high');
    });
});
