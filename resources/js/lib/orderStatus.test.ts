import { describe, expect, it } from 'vitest';
import { orderStatusBadgeClass, orderStatusLabel } from '@/lib/orderStatus';

describe('orderStatusLabel', () => {
    it('returns the German label for a known status', () => {
        expect(orderStatusLabel('paid')).toBe('Bezahlt');
        expect(orderStatusLabel('pending')).toBe('Ausstehend');
    });

    it('falls back to the raw status string for unknown values', () => {
        expect(orderStatusLabel('processing')).toBe('processing');
    });
});

describe('orderStatusBadgeClass', () => {
    it('returns the badge class for a known status', () => {
        expect(orderStatusBadgeClass('paid')).toBe(
            'bg-green-100 text-green-700',
        );
    });

    it('falls back to the muted badge class for unknown values', () => {
        expect(orderStatusBadgeClass('processing')).toBe(
            'bg-muted text-muted-foreground',
        );
    });
});
