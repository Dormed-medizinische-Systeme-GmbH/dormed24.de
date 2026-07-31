import { afterEach, describe, expect, it, vi } from 'vitest';
import { IsMobile } from '@/hooks/is-mobile.svelte';

function mockMatchMedia(matches: boolean) {
    const mediaQueryList = {
        matches,
        media: '',
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
    };
    const matchMediaSpy = vi.fn().mockReturnValue(mediaQueryList);

    vi.stubGlobal('matchMedia', matchMediaSpy);

    return matchMediaSpy;
}

describe('IsMobile', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('reports true when the viewport matches the mobile breakpoint', () => {
        mockMatchMedia(true);

        expect(new IsMobile().current).toBe(true);
    });

    it('reports false when the viewport does not match', () => {
        mockMatchMedia(false);

        expect(new IsMobile().current).toBe(false);
    });

    it('queries the default 768px breakpoint as max-width: 767px', () => {
        const matchMediaSpy = mockMatchMedia(false);

        new IsMobile();

        expect(matchMediaSpy).toHaveBeenCalledWith('(max-width: 767px)');
    });

    it('builds the query from a custom breakpoint', () => {
        const matchMediaSpy = mockMatchMedia(false);

        new IsMobile(1024);

        expect(matchMediaSpy).toHaveBeenCalledWith('(max-width: 1023px)');
    });
});
