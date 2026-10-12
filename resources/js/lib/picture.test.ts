import { describe, expect, it } from 'vitest';
import { avifSrc } from '@/lib/picture';

describe('avifSrc', () => {
    it('tauscht nur die Endung der WebP-URL', () => {
        expect(avifSrc('/storage/products/7/abc.webp')).toBe(
            '/storage/products/7/abc.avif',
        );
        expect(avifSrc('https://cdn.example/products/7/abc.WEBP')).toBe(
            'https://cdn.example/products/7/abc.avif',
        );
    });

    it('behält Query und Fragment', () => {
        expect(avifSrc('/storage/a.webp?v=3#x')).toBe('/storage/a.avif?v=3#x');
    });

    it('liefert null für Altbestand ohne WebP', () => {
        expect(avifSrc('/storage/products/7/abc.jpg')).toBeNull();
        expect(avifSrc('/storage/a.webp.jpg')).toBeNull();
    });
});
