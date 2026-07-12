<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import { fetchJson } from '@/lib/http';
    import checkout from '@/routes/checkout';
    import paypalOrder from '@/routes/paypal/order';

    let {
        clientId = '',
        disabled = false,
    }: {
        clientId?: string;
        disabled?: boolean;
    } = $props();

    let containerId = $state(
        `paypal-button-${Math.random().toString(36).slice(2, 9)}`,
    );
    let isProcessing = $state(false);
    let errorMessage = $state<string | null>(null);

    onMount(() => {
        loadPayPalSDK(clientId);
    });

    function loadPayPalSDK(clientId: string) {
        if (!clientId || clientId.length < 10) {
            errorMessage =
                'PayPal ist nicht konfiguriert. Bitte hinterlegen Sie eine gültige Client-ID in den Einstellungen.';

            return;
        }

        const script = document.createElement('script');
        script.src = `https://www.paypal.com/sdk/js?client-id=${clientId}&currency=EUR&locale=de_DE`;
        script.async = true;
        script.onload = () => renderButtons();
        script.onerror = () => {
            errorMessage =
                'PayPal SDK konnte nicht geladen werden. Bitte prüfen Sie die Client-ID in den Einstellungen.';
        };
        document.head.appendChild(script);
    }

    function renderButtons() {
        if (typeof paypal === 'undefined') {
            errorMessage = 'PayPal SDK nicht verfügbar.';

            return;
        }

        const container = document.getElementById(containerId);

        if (!container) {
            return;
        }

        paypal
            .Buttons({
                style: {
                    layout: 'vertical',
                    color: 'blue',
                    shape: 'rect',
                    label: 'paypal',
                },
                createOrder: async (): Promise<string> => {
                    // Normally unreachable: the overlay above blocks the click
                    // while disabled. Kept as a defense-in-depth fallback -
                    // the server re-validates agreed_to_terms regardless
                    // (PlaceOrderRequest).
                    if (disabled) {
                        errorMessage =
                            'Bitte akzeptieren Sie die AGB und vervollständigen Sie Ihre Lieferadresse.';

                        throw new Error(errorMessage);
                    }

                    isProcessing = true;
                    errorMessage = null;

                    const { ok, data } = await fetchJson<{
                        id?: string;
                        error?: string;
                    }>(paypalOrder.create.url(), {
                        method: 'POST',
                        body: { agreed_to_terms: !disabled },
                    });

                    if (!ok || !data.id) {
                        isProcessing = false;

                        throw new Error(
                            data.error ||
                                'PayPal-Order konnte nicht erstellt werden.',
                        );
                    }

                    return data.id;
                },
                onApprove: async (approval: { orderID: string }) => {
                    isProcessing = true;

                    const { ok, data } = await fetchJson<{ error?: string }>(
                        paypalOrder.capture.url(),
                        {
                            method: 'POST',
                            body: { paypal_order_id: approval.orderID },
                        },
                    );

                    if (!ok) {
                        errorMessage =
                            data.error ||
                            'Zahlung konnte nicht abgeschlossen werden.';
                        isProcessing = false;

                        return;
                    }

                    // Redirect to success page
                    router.visit(
                        checkout.success.url({
                            query: { paypal_order_id: approval.orderID },
                        }),
                    );
                },
                onCancel: () => {
                    errorMessage = 'Zahlung abgebrochen.';
                    isProcessing = false;
                },
                onError: (err: unknown) => {
                    errorMessage =
                        'Ein PayPal-Fehler ist aufgetreten. Bitte versuchen Sie es erneut.';
                    isProcessing = false;
                    console.error('PayPal error:', err);
                },
            })
            .render(`#${containerId}`);
    }
</script>

<div class="paypal-button-wrapper">
    {#if errorMessage}
        <div class="mb-3 rounded-md bg-red-50 p-3 text-sm text-red-700">
            {errorMessage}
        </div>
    {/if}

    <div class="relative">
        <div id={containerId} class="min-h-[40px]"></div>

        {#if disabled}
            <!-- PayPal's rendered button lives in an iframe (inline z-index:
            100, set by their zoid component) and can't be disabled via the
            disabled attribute; this overlay must sit above that z-index to
            actually block the click before it reaches the button, so
            createOrder's own guard (kept as defense-in-depth) is never hit
            in normal use. -->
            <button
                type="button"
                class="absolute inset-0 z-[200] cursor-not-allowed bg-transparent"
                aria-label="Bitte akzeptieren Sie die AGB und vervollständigen Sie Ihre Lieferadresse."
                onclick={() => {
                    errorMessage =
                        'Bitte akzeptieren Sie die AGB und vervollständigen Sie Ihre Lieferadresse.';
                }}
            ></button>
        {/if}
    </div>

    {#if isProcessing}
        <div class="mt-2 flex items-center justify-center gap-2">
            <div
                class="size-4 animate-spin rounded-full border-2 border-brand-accent border-t-transparent"
            ></div>
            <span class="text-sm text-gray-600">Zahlung wird verarbeitet…</span>
        </div>
    {/if}
</div>
