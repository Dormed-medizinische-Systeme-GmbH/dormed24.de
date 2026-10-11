<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { onMount, tick } from 'svelte';
    import { fetchJson } from '@/lib/http';
    import checkout from '@/routes/checkout';
    import paypalOrder from '@/routes/paypal/order';

    let {
        clientId = '',
        disabled = false,
        row = false,
    }: {
        clientId?: string;
        disabled?: boolean;
        /** Buttons nebeneinander statt untereinander. */
        row?: boolean;
    } = $props();

    // Ein eigener PayPal-Button je Zahlart, in dieser Reihenfolge. Der Wert ist
    // die fundingSource des PayPal-SDK und zugleich "funding_source" am Server
    // (PaymentMethod).
    const FUNDING_SOURCES = [
        { id: 'paypal', color: 'gold' },
        { id: 'sepa', color: 'black' },
        { id: 'card', color: 'black' },
    ] as const;

    type FundingSource = (typeof FUNDING_SOURCES)[number]['id'];

    const containerPrefix = `paypal-button-${Math.random().toString(36).slice(2, 9)}`;
    let isProcessing = $state(false);
    let errorMessage = $state<string | null>(null);
    let eligibleSources = $state<FundingSource[]>([]);

    // Schalter der gerenderten Buttons (kommen aus onInit). Kein $state: nur
    // der Effekt unten liest sie, und der reagiert auf "disabled".
    const buttonActions: Partial<Record<FundingSource, PayPalButtonActions>> =
        {};

    $effect(() => {
        const isDisabled = disabled;

        for (const actions of Object.values(buttonActions)) {
            if (isDisabled) {
                actions.disable();
            } else {
                actions.enable();
            }
        }
    });

    const GUIDANCE =
        'Bitte akzeptieren Sie die AGB und vervollständigen Sie Ihre Lieferadresse.';

    onMount(() => {
        loadPayPalSDK(clientId);
    });

    function loadPayPalSDK(clientId: string) {
        if (!clientId || clientId.length < 10) {
            errorMessage =
                'PayPal ist nicht konfiguriert. Bitte hinterlegen Sie eine gültige Client-ID in den Einstellungen.';

            return;
        }

        // Schon geladen (z. B. nach einem Layout-Wechsel): direkt rendern.
        if (typeof paypal !== 'undefined') {
            renderButtons();

            return;
        }

        const script = document.createElement('script');
        script.src = `https://www.paypal.com/sdk/js?client-id=${clientId}&currency=EUR&locale=de_DE&components=buttons&enable-funding=sepa,card`;
        script.async = true;
        script.onload = () => renderButtons();
        script.onerror = () => {
            errorMessage =
                'PayPal SDK konnte nicht geladen werden. Bitte prüfen Sie die Client-ID in den Einstellungen.';
        };
        document.head.appendChild(script);
    }

    function containerId(source: FundingSource): string {
        return `${containerPrefix}-${source}`;
    }

    async function renderButtons() {
        if (typeof paypal === 'undefined') {
            errorMessage = 'PayPal SDK nicht verfügbar.';

            return;
        }

        try {
            // Nur Zahlarten anzeigen, die PayPal für diesen Käufer anbietet; die
            // Container müssen dafür erst im DOM stehen.
            const buttons = FUNDING_SOURCES.map((source) => ({
                source,
                instance: paypal!.Buttons(buttonConfig(source)),
            })).filter(({ instance }) => instance.isEligible());

            eligibleSources = buttons.map(({ source }) => source.id);
            await tick();

            for (const { source, instance } of buttons) {
                const container = document.getElementById(
                    containerId(source.id),
                );

                if (container) {
                    instance.render(container);
                }
            }
        } catch (err) {
            errorMessage =
                'PayPal-Buttons konnten nicht angezeigt werden. Bitte laden Sie die Seite neu.';
            console.error('PayPal render error:', err);
        }
    }

    function buttonConfig(source: (typeof FUNDING_SOURCES)[number]) {
        return {
            fundingSource: source.id,
            style: {
                layout: 'vertical',
                color: source.color,
                shape: 'rect',
                // Das SDK verlangt eine Zahl (25-55) und wirft sonst beim Rendern.
                height: 45,
            },
            // Button erst freigeben, wenn AGB und Adresse passen (graut ihn
            // sonst aus); onClick fängt den Rest ab.
            onInit: (_data: unknown, actions: PayPalButtonActions) => {
                buttonActions[source.id] = actions;

                if (disabled) {
                    actions.disable();
                }
            },
            onClick: (_data: unknown, actions: PayPalClickActions) => {
                if (disabled) {
                    errorMessage = GUIDANCE;

                    return actions.reject();
                }

                return actions.resolve();
            },
            createOrder: async (): Promise<string> => {
                // Normally unreachable: the overlay above blocks the click
                // while disabled. Kept as a defense-in-depth fallback -
                // the server re-validates agreed_to_terms regardless
                // (CreatePayPalOrderRequest).
                if (disabled) {
                    errorMessage = GUIDANCE;

                    throw new Error(errorMessage);
                }

                isProcessing = true;
                errorMessage = null;

                const { ok, data } = await fetchJson<{
                    id?: string;
                    error?: string;
                }>(paypalOrder.create.url(), {
                    method: 'POST',
                    body: {
                        agreed_to_terms: !disabled,
                        funding_source: source.id,
                    },
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
        };
    }
</script>

<div class="paypal-button-wrapper">
    {#if errorMessage}
        <div
            class="mb-3 rounded-md bg-destructive/10 p-3 text-sm text-destructive"
        >
            {errorMessage}
        </div>
    {/if}

    <div class="relative">
        <div
            class={row
                ? 'grid gap-3 sm:grid-cols-[repeat(auto-fit,minmax(11rem,1fr))]'
                : 'flex flex-col gap-3'}
        >
            {#each eligibleSources as source (source)}
                <div id={containerId(source)} class="min-h-[45px]"></div>
            {/each}
        </div>

        {#if disabled}
            <!-- Die Buttons werden über actions.disable() ausgegraut. Ein
            deaktivierter Button im iframe nimmt aber keinen Klick an und
            zeigt deshalb auch keinen Hinweis; diese Fläche liegt über dem
            iframe (inline z-index: 100) und liefert die Meldung. -->
            <button
                type="button"
                class="absolute inset-0 z-[200] cursor-not-allowed bg-transparent"
                aria-label={GUIDANCE}
                onclick={() => {
                    errorMessage = GUIDANCE;
                }}
            ></button>
        {/if}
    </div>

    {#if isProcessing}
        <div class="mt-2 flex items-center justify-center gap-2">
            <div
                class="size-4 animate-spin rounded-full border-2 border-brand-accent border-t-transparent"
            ></div>
            <span class="text-sm text-muted-foreground"
                >Zahlung wird verarbeitet…</span
            >
        </div>
    {/if}
</div>
