import { describe, expect, it } from 'vitest';
import { formatDate } from '@/lib/date';

describe('formatDate', () => {
    it('formats a Date as zero-padded DD.MM', () => {
        expect(formatDate(new Date(2026, 6, 7))).toBe('07.07.');
    });

    it('formats an ISO date string', () => {
        expect(formatDate('2026-01-09')).toBe('09.01.');
    });

    it('includes the year when withYear is set', () => {
        expect(formatDate('2026-07-07', { withYear: true })).toBe('07.07.2026');
    });
});
