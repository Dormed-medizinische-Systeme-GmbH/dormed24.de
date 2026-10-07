import { beforeEach, describe, expect, it, vi } from 'vitest';

const submitMock = vi.fn();

vi.mock('@inertiajs/svelte', () => ({
    useHttp: () => ({ submit: submitMock }),
}));

const { twoFactorAuthState } = await import('@/lib/twoFactorAuth.svelte');

describe('twoFactorAuthState', () => {
    beforeEach(() => {
        submitMock.mockReset();
        // Module-level singleton state - reset before every test.
        twoFactorAuthState().clearTwoFactorAuthData();
    });

    it('reports no setup data before anything has been fetched', () => {
        expect(twoFactorAuthState().hasSetupData()).toBe(false);
    });

    describe('fetchQrCode', () => {
        it('stores the returned SVG on success', async () => {
            submitMock.mockResolvedValueOnce({
                svg: '<svg>qr</svg>',
                url: 'otpauth://...',
            });

            const api = twoFactorAuthState();
            await api.fetchQrCode();

            expect(api.state.qrCodeSvg).toBe('<svg>qr</svg>');
        });

        it('records an error and clears the SVG on failure', async () => {
            submitMock.mockRejectedValueOnce(new Error('network error'));

            const api = twoFactorAuthState();
            await api.fetchQrCode();

            expect(api.state.qrCodeSvg).toBeNull();
            expect(api.state.errors).toContain('Failed to fetch QR code');
        });
    });

    describe('fetchSetupKey', () => {
        it('stores the returned secret key on success', async () => {
            submitMock.mockResolvedValueOnce({ secretKey: 'ABCD1234' });

            const api = twoFactorAuthState();
            await api.fetchSetupKey();

            expect(api.state.manualSetupKey).toBe('ABCD1234');
        });

        it('records an error and clears the key on failure', async () => {
            submitMock.mockRejectedValueOnce(new Error('network error'));

            const api = twoFactorAuthState();
            await api.fetchSetupKey();

            expect(api.state.manualSetupKey).toBeNull();
            expect(api.state.errors).toContain('Failed to fetch a setup key');
        });
    });

    describe('fetchSetupData', () => {
        it('fetches the QR code and setup key together and reports setup data as available', async () => {
            submitMock
                .mockResolvedValueOnce({ svg: '<svg>qr</svg>', url: 'x' })
                .mockResolvedValueOnce({ secretKey: 'ABCD1234' });

            const api = twoFactorAuthState();
            await api.fetchSetupData();

            expect(api.state.qrCodeSvg).toBe('<svg>qr</svg>');
            expect(api.state.manualSetupKey).toBe('ABCD1234');
            expect(api.hasSetupData()).toBe(true);
        });
    });

    describe('fetchRecoveryCodes', () => {
        it('stores the returned codes on success', async () => {
            submitMock.mockResolvedValueOnce(['code-1', 'code-2']);

            const api = twoFactorAuthState();
            await api.fetchRecoveryCodes();

            expect(api.state.recoveryCodesList).toEqual(['code-1', 'code-2']);
        });

        it('records an error and clears the list on failure', async () => {
            submitMock.mockRejectedValueOnce(new Error('network error'));

            const api = twoFactorAuthState();
            await api.fetchRecoveryCodes();

            expect(api.state.recoveryCodesList).toEqual([]);
            expect(api.state.errors).toContain(
                'Failed to fetch recovery codes',
            );
        });
    });

    describe('clearSetupData / clearTwoFactorAuthData', () => {
        it('clearSetupData resets QR code, key and errors but keeps recovery codes', async () => {
            submitMock.mockResolvedValueOnce(['code-1']);

            const api = twoFactorAuthState();
            await api.fetchRecoveryCodes();
            api.state.qrCodeSvg = '<svg/>';
            api.state.manualSetupKey = 'ABCD1234';

            api.clearSetupData();

            expect(api.state.qrCodeSvg).toBeNull();
            expect(api.state.manualSetupKey).toBeNull();
            expect(api.state.errors).toEqual([]);
            expect(api.state.recoveryCodesList).toEqual(['code-1']);
        });

        it('clearTwoFactorAuthData resets everything including recovery codes', async () => {
            submitMock.mockResolvedValueOnce(['code-1']);

            const api = twoFactorAuthState();
            await api.fetchRecoveryCodes();

            api.clearTwoFactorAuthData();

            expect(api.state.recoveryCodesList).toEqual([]);
            expect(api.hasSetupData()).toBe(false);
        });
    });
});
