import { render, screen } from '@testing-library/svelte';
import { tick } from 'svelte';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PayPalButtons from '@/components/PayPalButtons.svelte';

const { fetchJsonMock, routerVisitMock } = vi.hoisted(() => ({
    fetchJsonMock: vi.fn(),
    routerVisitMock: vi.fn(),
}));

vi.mock('@/lib/http', () => ({ fetchJson: fetchJsonMock }));
vi.mock('@inertiajs/svelte', () => ({ router: { visit: routerVisitMock } }));

type ButtonsConfig = {
    fundingSource: string;
    style: Record<string, unknown>;
    onInit: (
        data: unknown,
        actions: { enable: () => void; disable: () => void },
    ) => void;
    onClick: (
        data: unknown,
        actions: { resolve: () => Promise<void>; reject: () => Promise<void> },
    ) => Promise<void>;
    createOrder: () => Promise<string>;
    onApprove: (approval: { orderID: string }) => Promise<void>;
    onCancel: () => void;
    onError: (error: unknown) => void;
};

/** Renders the buttons, fires the SDK's load event and returns the configs
 * passed to paypal.Buttons() by funding source, the same way the real
 * component does it. `eligible` lists the funding sources PayPal offers. */
async function renderAndLoadSdk(
    props: { clientId?: string; disabled?: boolean } = {},
    eligible: string[] = ['paypal', 'sepa', 'card'],
): Promise<{
    configs: Record<string, ButtonsConfig>;
    renderMock: ReturnType<typeof vi.fn>;
}> {
    const configs: Record<string, ButtonsConfig> = {};
    const renderMock = vi.fn();
    render(PayPalButtons, {
        props: { clientId: 'sb-client-id-1234567890', ...props },
    });

    // Erst nach dem Rendern: bei bereits geladenem SDK wird kein Skript angehängt.
    vi.stubGlobal('paypal', {
        Buttons: vi.fn((c: ButtonsConfig) => {
            configs[c.fundingSource] = c;

            return {
                isEligible: () => eligible.includes(c.fundingSource),
                render: renderMock,
            };
        }),
    });

    const script = document.head.querySelector('script') as HTMLScriptElement;
    script.onload?.(new Event('load'));
    await tick();
    await tick();

    return { configs, renderMock };
}

describe('PayPalButtons', () => {
    beforeEach(() => {
        fetchJsonMock.mockReset();
        routerVisitMock.mockReset();
        document.head.innerHTML = '';
        vi.unstubAllGlobals();
    });

    it('shows a configuration error and loads no SDK script when clientId is missing', () => {
        render(PayPalButtons, { props: { clientId: '' } });

        expect(screen.getByText(/nicht konfiguriert/)).toBeInTheDocument();
        expect(document.head.querySelector('script')).toBeNull();
    });

    it('loads the PayPal SDK with the given client id', () => {
        render(PayPalButtons, {
            props: { clientId: 'sb-client-id-1234567890' },
        });

        const script = document.head.querySelector('script');

        expect(script?.src).toContain('client-id=sb-client-id-1234567890');
    });

    it('shows an error when the SDK script fails to load', async () => {
        render(PayPalButtons, {
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

    it('renders one button per eligible funding source, in order', async () => {
        const { configs, renderMock } = await renderAndLoadSdk();

        expect(Object.keys(configs)).toEqual(['paypal', 'sepa', 'card']);
        expect(renderMock).toHaveBeenCalledTimes(3);
    });

    it('renders no button for funding sources PayPal does not offer', async () => {
        const { renderMock } = await renderAndLoadSdk({}, ['paypal', 'card']);

        expect(renderMock).toHaveBeenCalledTimes(2);
    });

    it('loads the SDK with the sepa and card funding enabled', () => {
        render(PayPalButtons, {
            props: { clientId: 'sb-client-id-1234567890' },
        });

        expect(document.head.querySelector('script')?.src).toContain(
            'enable-funding=sepa,card',
        );
    });

    it('passes a numeric style.height within the SDK range (a string makes the SDK throw)', async () => {
        const { configs } = await renderAndLoadSdk();

        for (const config of Object.values(configs)) {
            expect(typeof config.style.height).toBe('number');
            expect(config.style.height as number).toBeGreaterThanOrEqual(25);
            expect(config.style.height as number).toBeLessThanOrEqual(55);
        }
    });

    it('shows an error instead of failing silently when the SDK throws while rendering', async () => {
        vi.spyOn(console, 'error').mockImplementation(() => {});

        render(PayPalButtons, {
            props: { clientId: 'sb-client-id-1234567890' },
        });
        vi.stubGlobal('paypal', {
            Buttons: vi.fn(() => {
                throw new TypeError('Expected style.height to be a number');
            }),
        });
        (document.head.querySelector('script') as HTMLScriptElement).onload?.(
            new Event('load'),
        );
        await tick();
        await tick();

        expect(
            screen.getByText(/Buttons konnten nicht angezeigt werden/),
        ).toBeInTheDocument();
    });

    it('renders directly without a second script when the SDK is already loaded', async () => {
        const renderMock = vi.fn();
        vi.stubGlobal('paypal', {
            Buttons: vi.fn(() => ({
                isEligible: () => true,
                render: renderMock,
            })),
        });

        render(PayPalButtons, {
            props: { clientId: 'sb-client-id-1234567890' },
        });
        await tick();
        await tick();

        expect(document.head.querySelector('script')).toBeNull();
        expect(renderMock).toHaveBeenCalledTimes(3);
    });

    describe('createOrder', () => {
        it('sends the funding source of the clicked button', async () => {
            fetchJsonMock.mockResolvedValueOnce({
                ok: true,
                status: 200,
                data: { id: 'PAY-2', status: 'CREATED' },
            });

            const { configs } = await renderAndLoadSdk();

            await configs.sepa.createOrder();

            expect(fetchJsonMock).toHaveBeenCalledWith(
                expect.stringContaining('/paypal/order/create'),
                {
                    method: 'POST',
                    body: { agreed_to_terms: true, funding_source: 'sepa' },
                },
            );
        });

        it('posts agreed_to_terms via fetchJson and resolves with the PayPal order id', async () => {
            fetchJsonMock.mockResolvedValueOnce({
                ok: true,
                status: 200,
                data: { id: 'PAY-1', status: 'CREATED' },
            });

            const { configs } = await renderAndLoadSdk();
            const config = configs.paypal;
            const orderId = await config.createOrder();

            expect(fetchJsonMock).toHaveBeenCalledWith(
                expect.stringContaining('/paypal/order/create'),
                {
                    method: 'POST',
                    body: { agreed_to_terms: true, funding_source: 'paypal' },
                },
            );
            expect(orderId).toBe('PAY-1');
        });

        it('rejects without calling fetchJson when disabled', async () => {
            const { configs } = await renderAndLoadSdk({ disabled: true });
            const config = configs.paypal;

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

            const { configs } = await renderAndLoadSdk();
            const config = configs.paypal;

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

            const { configs } = await renderAndLoadSdk();
            const config = configs.paypal;
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

            const { configs } = await renderAndLoadSdk();
            const config = configs.paypal;
            await config.onApprove({ orderID: 'PAY-1' });
            await tick();

            expect(routerVisitMock).not.toHaveBeenCalled();
            expect(
                screen.getByText('Zahlung konnte nicht abgeschlossen werden.'),
            ).toBeInTheDocument();
        });
    });

    it('shows a cancellation message via onCancel', async () => {
        const { configs } = await renderAndLoadSdk();
        const config = configs.paypal;

        config.onCancel();
        await tick();

        expect(screen.getByText('Zahlung abgebrochen.')).toBeInTheDocument();
    });

    it('shows a generic error via onError', async () => {
        const { configs } = await renderAndLoadSdk();
        const config = configs.paypal;

        config.onError(new Error('boom'));
        await tick();

        expect(
            screen.getByText(/Ein PayPal-Fehler ist aufgetreten/),
        ).toBeInTheDocument();
    });

    it('renders a blocking overlay with a guidance label when disabled', () => {
        render(PayPalButtons, {
            props: { clientId: 'sb-client-id-1234567890', disabled: true },
        });

        expect(
            screen.getByLabelText(
                'Bitte akzeptieren Sie die AGB und vervollständigen Sie Ihre Lieferadresse.',
            ),
        ).toBeInTheDocument();
    });

    describe('disabling', () => {
        const actionsMock = () => ({
            enable: vi.fn(),
            disable: vi.fn(),
        });

        it('disables a button in onInit while the terms are not accepted', async () => {
            const { configs } = await renderAndLoadSdk({ disabled: true });
            const actions = actionsMock();

            configs.sepa.onInit({}, actions);

            expect(actions.disable).toHaveBeenCalled();
            expect(actions.enable).not.toHaveBeenCalled();
        });

        it('does not disable a button in onInit when it is allowed', async () => {
            const { configs } = await renderAndLoadSdk();
            const actions = actionsMock();

            configs.card.onInit({}, actions);

            expect(actions.disable).not.toHaveBeenCalled();
        });

        it('rejects the click with a hint when disabled', async () => {
            const { configs } = await renderAndLoadSdk({ disabled: true });
            const actions = {
                resolve: vi.fn().mockResolvedValue(undefined),
                reject: vi.fn().mockResolvedValue(undefined),
            };

            await configs.paypal.onClick({}, actions);
            await tick();

            expect(actions.reject).toHaveBeenCalled();
            expect(actions.resolve).not.toHaveBeenCalled();
            expect(
                screen.getAllByText(/AGB und vervollständigen/).length,
            ).toBeGreaterThan(0);
        });

        it('resolves the click when allowed', async () => {
            const { configs } = await renderAndLoadSdk();
            const actions = {
                resolve: vi.fn().mockResolvedValue(undefined),
                reject: vi.fn().mockResolvedValue(undefined),
            };

            await configs.paypal.onClick({}, actions);

            expect(actions.resolve).toHaveBeenCalled();
        });
    });
});
