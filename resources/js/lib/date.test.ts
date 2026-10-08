import { describe, expect, it } from 'vitest';
import {
    formatDate,
    formatDateTime,
    fromDateTimeLocal,
    toDateTimeLocal,
} from '@/lib/date';

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

describe('formatDateTime', () => {
    it('formats date and time in local time', () => {
        expect(formatDateTime(new Date(2026, 9, 10, 9, 5))).toBe(
            '10.10.2026, 09:05',
        );
    });
});

describe('datetime-local conversion', () => {
    it('round-trips a local input value through UTC', () => {
        const iso = fromDateTimeLocal('2026-10-10T09:30');

        expect(iso).toBe(new Date(2026, 9, 10, 9, 30).toISOString());
        expect(toDateTimeLocal(iso)).toBe('2026-10-10T09:30');
    });

    it('maps empty values to null and back to an empty string', () => {
        expect(fromDateTimeLocal('')).toBeNull();
        expect(toDateTimeLocal(null)).toBe('');
    });
});
