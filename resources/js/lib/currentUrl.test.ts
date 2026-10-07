import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/svelte', () => ({
    page: { url: '/products/5?sort=asc' },
}));

const { currentUrlState } = await import('@/lib/currentUrl.svelte');

describe('currentUrlState', () => {
    it('exposes the pathname of the current Inertia page URL, without the query string', () => {
        expect(currentUrlState().currentUrl).toBe('/products/5');
    });

    describe('isCurrentUrl', () => {
        it('is true when the resolved href matches the current path exactly', () => {
            const { isCurrentUrl } = currentUrlState();

            expect(isCurrentUrl('/products/5', '/products/5')).toBe(true);
        });

        it('is false for a parent path, not just an exact match', () => {
            const { isCurrentUrl } = currentUrlState();

            expect(isCurrentUrl('/products', '/products/5')).toBe(false);
        });

        it('resolves an object href via its .url before comparing', () => {
            const { isCurrentUrl } = currentUrlState();

            expect(
                isCurrentUrl(
                    { url: '/products/5', method: 'get' },
                    '/products/5',
                ),
            ).toBe(true);
        });
    });

    describe('isCurrentOrParentUrl', () => {
        it('is true when the current path is nested under the href', () => {
            const { isCurrentOrParentUrl } = currentUrlState();

            expect(isCurrentOrParentUrl('/products', '/products/5')).toBe(true);
        });

        it('is false for an unrelated path', () => {
            const { isCurrentOrParentUrl } = currentUrlState();

            expect(isCurrentOrParentUrl('/checkout', '/products/5')).toBe(
                false,
            );
        });
    });

    describe('whenCurrentUrl', () => {
        it('returns ifTrue when the href matches', () => {
            const { whenCurrentUrl } = currentUrlState();

            expect(
                whenCurrentUrl(
                    '/products/5',
                    '/products/5',
                    'active',
                    'inactive',
                ),
            ).toBe('active');
        });

        it('returns ifFalse when the href does not match', () => {
            const { whenCurrentUrl } = currentUrlState();

            expect(
                whenCurrentUrl(
                    '/checkout',
                    '/products/5',
                    'active',
                    'inactive',
                ),
            ).toBe('inactive');
        });
    });
});
