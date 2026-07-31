import { render, screen } from '@testing-library/svelte';
import { tick } from 'svelte';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PayPalButton from '@/components/PayPalButton.svelte';

const { fetchJsonMock, routerVisitMock } = vi.hoisted(() => ({
    fetchJsonMock: vi.fn(),
    routerVisitMock: vi.fn(),
}));

vi.mock('@/lib/http', () => ({ fetchJson: fetchJsonMock }));
vi.mock('@inertiajs/svelte', () => ({ router: { visit: routerVisitMock } }));

type ButtonsConfig = {
    createOrder: () => Promise<string>;
    onApprove: (approval: { orderID: string }) => Promise<void>;
    onCancel: () => void;
    onError: (error: unknown) => void;
};

/** Renders the button, fires the SDK's load event and captures the config
 * object passed to paypal.Buttons(), the same way the real button does it. */
async function renderAndLoadSdk(
    props: { clientId?: string; disabled?: boolean } = {},
): Promise<ButtonsConfig> {
    let config!: ButtonsConfig;
    vi.stubGlobal('paypal', {
        Buttons: vi.fn((c: ButtonsConfig) => {
            config = c;

            return { render: vi.fn() };
        }),
    });

    render(PayPalButton, {
        props: { clientId: 'sb-client-id-1234567890', ...props },
    });

    const script = document.head.querySelector('script') as HTMLScriptElement;
    script.onload?.(new Event('load'));
    await tick();

    return config;
}

describe('PayPalButton', () => {
    beforeEach(() => {
        fetchJsonMock.mockReset();
        routerVisitMock.mockReset();
        document.head.innerHTML = '';
    });

    it('shows a configuration error and loads no SDK script when clientId is missing', () => {
        render(PayPalButton, { props: { clientId: '' } });

        expect(screen.getByText(/nicht konfiguriert/)).toBeInTheDocument();
        expect(document.head.querySelector('script')).toBeNull();
    });

    it('loads the PayPal SDK with the given client id', () => {
        render(PayPalButton, {
            props: { clientId: 'sb-client-id-1234567890' },
        });

        const script = document.head.querySelector('script');

        expect(script?.src).toContain('client-id=sb-client-id-1234567890');
    });

    it('shows an error when the SDK script fails to load', async () => {
        render(PayPalButton, {
            props: { clientId: 'sb-client-id-1234567890' },
        });
        const script = document.head.querySelector(
            'script',
        ) as HTMLScriptElement;

        script.onerror?.(new Event('error'));
        await tick();

        expect(
            screen.getByText(/SDK konnte nicht geladen werden/),
        ).toBeInTheDocument();
    });

    describe('createOrder', () => {
        it('posts agreed_to_terms via fetchJson and resolves with the PayPal order id', async () => {
            fetchJsonMock.mockResolvedValueOnce({
                ok: true,
                status: 200,
                data: { id: 'PAY-1', status: 'CREATED' },
            });

            const config = await renderAndLoadSdk();
            const orderId = await config.createOrder();

            expect(fetchJsonMock).toHaveBeenCalledWith(
                expect.stringContaining('/paypal/order/create'),
                { method: 'POST', body: { agreed_to_terms: true } },
            );
            expect(orderId).toBe('PAY-1');
        });

        it('rejects without calling fetchJson when disabled', async () => {
            const config = await renderAndLoadSdk({ disabled: true });

            await expect(config.createOrder()).rejects.toThrow();
            expect(fetchJsonMock).not.toHaveBeenCalled();
        });

        it('rejects with the server error message when order creation fails', async () => {
            // The PayPal SDK (mocked away here) is what turns this rejection
            // into a visible error via onError - see the onError test below.
            fetchJsonMock.mockResolvedValueOnce({
                ok: false,
                status: 422,
                data: {
                    error: 'Bitte vervollständigen Sie Ihre Lieferadresse.',
                },
            });

            const config = await renderAndLoadSdk();

            await expect(config.createOrder()).rejects.toThrow(
                'Bitte vervollständigen Sie Ihre Lieferadresse.',
            );
        });
    });

    describe('onApprove', () => {
        it('captures the order and redirects to the success page', async () => {
            fetchJsonMock.mockResolvedValueOnce({
                ok: true,
                status: 200,
                data: {},
            });

            const config = await renderAndLoadSdk();
            await config.onApprove({ orderID: 'PAY-1' });

            expect(fetchJsonMock).toHaveBeenCalledWith(
                expect.stringContaining('/paypal/order/capture'),
                { method: 'POST', body: { paypal_order_id: 'PAY-1' } },
            );
            expect(routerVisitMock).toHaveBeenCalledWith(
                expect.stringContaining('paypal_order_id=PAY-1'),
            );
        });

        it('shows an error and does not redirect when capture fails', async () => {
            fetchJsonMock.mockResolvedValueOnce({
                ok: false,
                status: 500,
                data: { error: 'Zahlung konnte nicht abgeschlossen werden.' },
            });

            const config = await renderAndLoadSdk();
            await config.onApprove({ orderID: 'PAY-1' });
            await tick();

            expect(routerVisitMock).not.toHaveBeenCalled();
            expect(
                screen.getByText('Zahlung konnte nicht abgeschlossen werden.'),
            ).toBeInTheDocument();
        });
    });

    it('shows a cancellation message via onCancel', async () => {
        const config = await renderAndLoadSdk();

        config.onCancel();
        await tick();

        expect(screen.getByText('Zahlung abgebrochen.')).toBeInTheDocument();
    });

    it('shows a generic error via onError', async () => {
        const config = await renderAndLoadSdk();

        config.onError(new Error('boom'));
        await tick();

        expect(
            screen.getByText(/Ein PayPal-Fehler ist aufgetreten/),
        ).toBeInTheDocument();
    });

    it('renders a blocking overlay with a guidance label when disabled', () => {
        render(PayPalButton, {
            props: { clientId: 'sb-client-id-1234567890', disabled: true },
        });

        expect(
            screen.getByLabelText(
                'Bitte akzeptieren Sie die AGB und vervollständigen Sie Ihre Lieferadresse.',
            ),
        ).toBeInTheDocument();
    });
});
