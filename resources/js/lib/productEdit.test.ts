import { describe, expect, it } from 'vitest';
import {
    DEFAULT_EDIT_VARIANT,
    EDIT_VARIANTS,
    resolveEditVariant,
    withEditVariant,
} from '@/lib/productEdit';

describe('resolveEditVariant', () => {
    it('falls back to the default variant without a parameter', () => {
        expect(resolveEditVariant('/admin/products/5/edit')).toBe(
            DEFAULT_EDIT_VARIANT,
        );
    });

    it('reads a known variant, case-insensitively', () => {
        expect(resolveEditVariant('/admin/products/5/edit?variant=C')).toBe(
            'C',
        );
        expect(resolveEditVariant('/admin/products/5/edit?variant=e')).toBe(
            'E',
        );
    });

    it('ignores unknown variants', () => {
        expect(resolveEditVariant('/admin/products/5/edit?variant=Z')).toBe(
            DEFAULT_EDIT_VARIANT,
        );
        expect(resolveEditVariant('/admin/products/5/edit?variant=')).toBe(
            DEFAULT_EDIT_VARIANT,
        );
    });

    it('offers exactly the variants A to E', () => {
        expect(EDIT_VARIANTS.map((variant) => variant.key)).toEqual([
            'A',
            'B',
            'C',
            'D',
            'E',
        ]);
    });
});

describe('withEditVariant', () => {
    it('adds the parameter for non-default variants', () => {
        expect(withEditVariant('/admin/products/5/edit', 'D')).toBe(
            '/admin/products/5/edit?variant=D',
        );
    });

    it('removes the parameter for the default variant', () => {
        expect(withEditVariant('/admin/products/5/edit?variant=D', 'A')).toBe(
            '/admin/products/5/edit',
        );
    });

    it('keeps other query parameters and the hash', () => {
        expect(
            withEditVariant(
                'https://example.test/admin/products/5/edit?foo=1&variant=B#x',
                'C',
            ),
        ).toBe('/admin/products/5/edit?foo=1&variant=C#x');
        expect(
            withEditVariant('/admin/products/5/edit?foo=1&variant=B', 'A'),
        ).toBe('/admin/products/5/edit?foo=1');
    });
});
