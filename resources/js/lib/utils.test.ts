import { describe, expect, it } from 'vitest';
import { cn, toUrl } from '@/lib/utils';

describe('cn', () => {
    it('joins multiple class strings', () => {
        expect(cn('flex', 'items-center')).toBe('flex items-center');
    });

    it('drops falsy values', () => {
        expect(cn('flex', false, undefined, null, 'gap-2')).toBe('flex gap-2');
    });

    it('resolves conflicting Tailwind utilities in favor of the later class', () => {
        expect(cn('p-2', 'p-4')).toBe('p-4');
    });

    it('keeps non-conflicting utilities from both arguments', () => {
        expect(cn('text-sm font-bold', 'text-red-500')).toBe(
            'text-sm font-bold text-red-500',
        );
    });
});

describe('toUrl', () => {
    it('returns a string href unchanged', () => {
        expect(toUrl('/products')).toBe('/products');
    });

    it('extracts .url from an object href', () => {
        expect(toUrl({ url: '/checkout', method: 'get' })).toBe('/checkout');
    });
});
