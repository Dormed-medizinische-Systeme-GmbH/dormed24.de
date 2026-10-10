import { describe, expect, it } from 'vitest';
import { resolveLayoutVariant, withLayoutVariant } from '@/lib/layoutVariants';
const options = [
    { key: 'A', label: 'a' },
    { key: 'B', label: 'b' },
    { key: 'C', label: 'c' },
    { key: 'D', label: 'd' },
    { key: 'E', label: 'e' },
] as const;

describe('resolveLayoutVariant', () => {
    it('falls back to the first option without a parameter', () => {
        expect(resolveLayoutVariant('/admin/products/5/edit', options)).toBe(
            'A',
        );
    });

    it('reads a known variant, case-insensitively', () => {
        expect(
            resolveLayoutVariant('/admin/products/5/edit?variant=C', options),
        ).toBe('C');
        expect(
            resolveLayoutVariant('/admin/products/5/edit?variant=e', options),
        ).toBe('E');
    });

    it('ignores unknown or empty variants', () => {
        expect(
            resolveLayoutVariant('/admin/products/5/edit?variant=Z', options),
        ).toBe('A');
        expect(
            resolveLayoutVariant('/admin/products/5/edit?variant=', options),
        ).toBe('A');
    });

    it('works with any option list', () => {
        const orders = [
            { key: 'A', label: 'a' },
            { key: 'B', label: 'b' },
        ] as const;

        expect(resolveLayoutVariant('/x?variant=B', orders)).toBe('B');
        expect(resolveLayoutVariant('/x?variant=E', orders)).toBe('A');
    });
});

describe('withLayoutVariant', () => {
    it('adds the parameter for non-default variants', () => {
        expect(withLayoutVariant('/admin/orders/5', 'D', options)).toBe(
            '/admin/orders/5?variant=D',
        );
    });

    it('removes the parameter for the default variant', () => {
        expect(
            withLayoutVariant('/admin/orders/5?variant=D', 'A', options),
        ).toBe('/admin/orders/5');
    });

    it('keeps other query parameters and the hash', () => {
        expect(
            withLayoutVariant(
                'https://example.test/admin/orders/5?foo=1&variant=B#x',
                'C',
                options,
            ),
        ).toBe('/admin/orders/5?foo=1&variant=C#x');
        expect(
            withLayoutVariant('/admin/orders/5?foo=1&variant=B', 'A', options),
        ).toBe('/admin/orders/5?foo=1');
    });
});
