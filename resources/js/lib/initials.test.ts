import { describe, expect, it } from 'vitest';
import { getInitials, initialsApi } from '@/lib/initials';

describe('getInitials', () => {
    it('returns an empty string for undefined input', () => {
        expect(getInitials(undefined)).toBe('');
    });

    it('returns an empty string for an empty string', () => {
        expect(getInitials('')).toBe('');
    });

    it('uses the first letter for a single name', () => {
        expect(getInitials('Max')).toBe('M');
    });

    it('combines first and last name initials, uppercased', () => {
        expect(getInitials('max mustermann')).toBe('MM');
    });

    it('uses first and last of a multi-part name, ignoring the middle', () => {
        expect(getInitials('Max Erika Mustermann')).toBe('MM');
    });
});

describe('initialsApi', () => {
    it('exposes getInitials', () => {
        expect(initialsApi().getInitials('Max Mustermann')).toBe('MM');
    });
});
