import { describe, expect, it } from 'vitest';
import { formatPrice } from '@/lib/currency';

describe('formatPrice', () => {
    it('formats a number as EUR currency in German locale', () => {
        expect(formatPrice(19.99)).toBe('19,99 €');
    });

    it('formats a numeric string', () => {
        expect(formatPrice('1234.5')).toBe('1.234,50 €');
    });

    it('rounds to two decimal places', () => {
        expect(formatPrice(9.999)).toBe('10,00 €');
    });

    it('formats zero', () => {
        expect(formatPrice(0)).toBe('0,00 €');
    });
});
