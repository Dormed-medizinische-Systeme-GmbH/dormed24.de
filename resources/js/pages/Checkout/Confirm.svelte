<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import ChevronLeft from '@lucide/svelte/icons/chevron-left';
    import AddressForm from '@/components/AddressForm.svelte';
    import AppFooter from '@/components/AppFooter.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import PayPalButton from '@/components/PayPalButton.svelte';
    import ShopHeader from '@/components/ShopHeader.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Separator } from '@/components/ui/separator';
    import { formatPrice } from '@/lib/currency';
    import { login } from '@/routes';
    import checkout from '@/routes/checkout';
    import type { AddressData, Cart, Customer } from '@/types';

    let {
        cart,
        paypal_client_id,
    }: { cart: Cart; paypal_client_id?: string | null } = $props();

    let agreedToTerms = $state(false);
    // svelte-ignore state_referenced_locally
    let billingSameAsShipping = $state(cart.billing_address === null);

    // Local address state, initialized from cart
    // svelte-ignore state_referenced_locally
    let shippingAddress = $state<AddressData>({ ...cart.shipping_address });
    // svelte-ignore state_referenced_locally
    let billingAddress = $state<AddressData | null>(
        cart.billing_address ? { ...cart.billing_address } : null,
    );

    let addressErrors = $state<Record<string, string>>({});
    let isSavingAddress = $state(false);

    const auth = $derived(page.props.auth);
    const customer = $derived(auth.user as Customer | undefined);

    const selectedPayment = $derived(cart.selected_payment_method);
    const selectedProvider = $derived(selectedPayment?.provider ?? null);
    const isInvoice = $derived(selectedProvider === 'invoice');
    const isPayPal = $derived(selectedProvider === 'paypal');

    const addressComplete = $derived(
        shippingAddress.first_name !== '' &&
            shippingAddress.last_name !== '' &&
            shippingAddress.street !== '' &&
            shippingAddress.house_number !== '' &&
            shippingAddress.zip !== '' &&
            shippingAddress.city !== '',
    );

    function handleAddressUpdate(update: {
        prefix: string;
        key: string;
        value: string;
    }) {
        const { prefix, key, value } = update;

        if (prefix === 'shipping') {
            shippingAddress = { ...shippingAddress, [key]: value };
        } else if (prefix === 'billing') {
            billingAddress = {
                ...(billingAddress ?? defaultBillingAddress()),
                [key]: value,
            };
        }

        // Clear error for this field
        const errorKey = `${prefix}.${key}`;

        if (addressErrors[errorKey]) {
            const next = { ...addressErrors };
            delete next[errorKey];
            addressErrors = next;
        }
    }

    function defaultBillingAddress(): AddressData {
        return {
            company: '',
            salutation: '',
            first_name: '',
            last_name: '',
            street: '',
            house_number: '',
            address_line2: '',
            zip: '',
            city: '',
            country: 'DE',
            phone: '',
        };
    }

    function enableBillingAddress() {
        billingSameAsShipping = false;
        billingAddress = defaultBillingAddress();
    }

    function disableBillingAddress() {
        billingSameAsShipping = true;
        billingAddress = null;
    }

    function saveAddress() {
        isSavingAddress = true;
        addressErrors = {};

        const payload: {
            shipping_address: AddressData;
            billing_same_as_shipping: boolean;
            billing_address?: AddressData;
        } = {
            shipping_address: shippingAddress,
            billing_same_as_shipping: billingSameAsShipping,
        };

        if (!billingSameAsShipping && billingAddress) {
            payload.billing_address = billingAddress;
        }

        router.patch(checkout.address.update.url(), payload, {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => {
                // The server validates "shipping_address.first_name" etc.;
                // AddressForm looks errors up as "shipping.first_name".
                addressErrors = Object.fromEntries(
                    Object.entries(errors).map(([key, message]) => [
                        key.replace('_address.', '.'),
                        message,
                    ]),
                );
            },
            onFinish: () => {
                isSavingAddress = false;
            },
        });
    }

    function updatePayment(paymentMethod: string) {
        router.patch(
            checkout.payment.update.url(),
            { payment_method: paymentMethod },
            { preserveScroll: true, preserveState: true },
        );
    }

    function submitOrder() {
        router.post(
            checkout.submit.url(),
            { agreed_to_terms: agreedToTerms },
            { preserveScroll: true },
        );
    }

    // Hold-to-confirm
    const HOLD_DURATION = 1500;
    let holdProgress = $state(0);
    let holdStartTime: number | null = null;
    let holdRaf: number | null = null;

    function holdStart() {
        if (!agreedToTerms || cart.is_empty || !addressComplete) {
            return;
        }

        holdStartTime = performance.now();
        tick();
    }

    function tick() {
        if (holdStartTime === null) {
            return;
        }

        const elapsed = performance.now() - holdStartTime;
        holdProgress = Math.min(100, (elapsed / HOLD_DURATION) * 100);

        if (holdProgress < 100) {
            holdRaf = requestAnimationFrame(tick);
        } else {
            holdRaf = null;
            holdStartTime = null;
            submitOrder();
        }
    }

    function holdEnd() {
        if (holdRaf !== null) {
            cancelAnimationFrame(holdRaf);
            holdRaf = null;
        }

        holdStartTime = null;
        holdProgress = 0;
    }
</script>

<AppHead title="Bestellung abschließen" />

<div class="flex min-h-screen flex-col bg-gray-50">
    <ShopHeader />

    <main class="flex-1">
        <div class="mx-auto max-w-7xl px-4 py-8 lg:px-8">
            <Link
                href={checkout.index.url()}
                class="mb-6 inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800"
            >
                <ChevronLeft class="size-4" />
                Zurück zur Bearbeitung
            </Link>

            <h1 class="mb-8 text-3xl font-bold text-gray-900">
                Bestellung abschließen
            </h1>

            <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
                <div class="flex flex-1 flex-col gap-6">
                    <!-- AGB -->
                    <div class="rounded-lg border bg-white p-5">
                        <h2 class="mb-2 font-bold text-gray-900">
                            AGB und Widerrufsbelehrung
                        </h2>
                        <Separator class="mb-4" />
                        <p class="mb-3 text-sm text-gray-600">
                            Bitte beachten Sie die
                            <Link
                                href="/informationen/widerrufsbelehrung"
                                class="text-brand-accent hover:underline"
                            >
                                Widerrufsbelehrung
                            </Link>.
                        </p>
                        <label class="flex cursor-pointer items-start gap-3">
                            <Checkbox
                                bind:checked={agreedToTerms}
                                class="mt-0.5"
                            />
                            <span class="text-sm text-gray-700">
                                Ich habe die
                                <Link
                                    href="/unternehmen/agb"
                                    class="text-brand-accent hover:underline"
                                    >AGB</Link
                                >
                                gelesen und bin mit ihnen einverstanden.
                            </span>
                        </label>
                    </div>

                    <!-- Lieferadresse -->
                    <div class="rounded-lg border bg-white p-5">
                        <div class="mb-2 flex items-center justify-between">
                            <h2 class="font-bold text-gray-900">
                                Lieferadresse
                            </h2>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={isSavingAddress || !addressComplete}
                                onclick={saveAddress}
                            >
                                {isSavingAddress
                                    ? 'Speichere...'
                                    : 'Adresse speichern'}
                            </Button>
                        </div>
                        <Separator class="mb-4" />
                        <AddressForm
                            data={shippingAddress}
                            errors={addressErrors}
                            prefix="shipping"
                            legend=""
                            onUpdate={handleAddressUpdate}
                        />
                    </div>

                    <!-- Rechnungsadresse -->
                    <div class="rounded-lg border bg-white p-5">
                        <h2 class="mb-2 font-bold text-gray-900">
                            Rechnungsadresse
                        </h2>
                        <Separator class="mb-4" />
                        {#if billingSameAsShipping}
                            <p class="text-sm text-gray-600">
                                Identisch mit Lieferadresse.
                            </p>
                            <button
                                onclick={enableBillingAddress}
                                class="mt-2 text-sm text-brand-accent hover:underline"
                            >
                                Abweichende Rechnungsadresse eingeben
                            </button>
                        {:else}
                            <AddressForm
                                data={billingAddress ?? defaultBillingAddress()}
                                errors={addressErrors}
                                prefix="billing"
                                legend=""
                                onUpdate={handleAddressUpdate}
                            />
                            <button
                                onclick={disableBillingAddress}
                                class="mt-2 text-sm text-brand-accent hover:underline"
                            >
                                Wie Lieferadresse verwenden
                            </button>
                        {/if}
                    </div>
                </div>

                <div class="w-full lg:w-80 xl:w-88">
                    <div class="rounded-lg border bg-white p-5">
                        <h2 class="mb-4 text-xl font-bold text-gray-900">
                            Zusammenfassung
                        </h2>

                        <div class="flex flex-col gap-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Zwischensumme</span>
                                <span class="font-medium"
                                    >{formatPrice(cart.subtotal)}*</span
                                >
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Versandkosten</span>
                                <span class="font-medium"
                                    >{formatPrice(cart.shipping_total)}*</span
                                >
                            </div>
                            <div class="my-2 border-t pt-2">
                                <div class="flex justify-between">
                                    <span class="font-bold text-gray-900"
                                        >Gesamtsumme</span
                                    >
                                    <span class="font-bold text-gray-900"
                                        >{formatPrice(cart.total)}*</span
                                    >
                                </div>
                            </div>
                            <div class="flex justify-between text-gray-500">
                                <span>Gesamtnettosumme</span>
                                <span>{formatPrice(cart.net_total)}</span>
                            </div>
                            <div class="flex justify-between text-gray-500">
                                <span>zzgl. {cart.vat_rate} % MwSt.</span>
                                <span>{formatPrice(cart.vat_amount)}</span>
                            </div>
                        </div>

                        {#if cart.payment_methods.length > 1}
                            <div class="mt-4 border-t pt-4">
                                <h3
                                    class="mb-3 text-sm font-bold text-gray-900"
                                >
                                    Zahlungsart
                                </h3>
                                <div class="flex flex-col gap-3">
                                    {#each cart.payment_methods as method (method.id)}
                                        <label
                                            class="flex cursor-pointer items-start gap-3"
                                        >
                                            <input
                                                type="radio"
                                                name="payment"
                                                value={method.id}
                                                checked={method.selected}
                                                onchange={() =>
                                                    updatePayment(method.id)}
                                                class="mt-0.5 accent-primary"
                                            />
                                            <span class="text-sm">
                                                <span
                                                    class="font-semibold text-gray-900"
                                                    >{method.label}</span
                                                >
                                                {#if method.description}
                                                    <br />
                                                    <span class="text-gray-500"
                                                        >{method.description}</span
                                                    >
                                                {/if}
                                            </span>
                                        </label>
                                    {/each}
                                </div>
                            </div>
                        {/if}

                        {#if customer}
                            {#if isInvoice}
                                {@const isDisabled =
                                    !agreedToTerms ||
                                    cart.is_empty ||
                                    !addressComplete}
                                <div class="mt-6">
                                    <button
                                        class="relative w-full overflow-hidden rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white transition-opacity select-none"
                                        disabled={isDisabled}
                                        onpointerdown={holdStart}
                                        onpointerup={holdEnd}
                                        onpointerleave={holdEnd}
                                        onpointercancel={holdEnd}
                                        style:opacity={isDisabled ? '0.5' : '1'}
                                        style:cursor={isDisabled
                                            ? 'not-allowed'
                                            : 'pointer'}
                                    >
                                        <span
                                            class="pointer-events-none absolute inset-0 origin-left transition-none"
                                            style="background:rgba(255,255,255,0.15);transform:scaleX({holdProgress /
                                                100});transform-origin:left;"
                                        ></span>
                                        <span class="relative">
                                            {#if holdProgress > 0}
                                                Gedrückt halten… {Math.round(
                                                    holdProgress,
                                                )}&nbsp;%
                                            {:else}
                                                Kaufen auf Rechnung
                                            {/if}
                                        </span>
                                    </button>
                                </div>
                                <p class="mt-3 text-sm text-gray-500">
                                    Halten Sie den Button gedrückt, um
                                    verbindlich zu bestellen. Sie erhalten eine
                                    Bestätigungsmail mit Bankverbindung.
                                </p>
                            {:else if isPayPal && paypal_client_id}
                                <div class="mt-6">
                                    <PayPalButton
                                        clientId={paypal_client_id ?? ''}
                                        disabled={!agreedToTerms ||
                                            !addressComplete}
                                    />
                                </div>
                            {:else}
                                <p class="mt-3 text-sm text-gray-500">
                                    Kein aktiver Zahlungsanbieter ausgewählt.
                                </p>
                            {/if}
                        {:else}
                            <Button
                                asChild
                                class="mt-6 w-full bg-primary text-white hover:bg-primary/90"
                            >
                                {#snippet children(props)}
                                    <Link
                                        href={login.url({
                                            query: {
                                                redirect:
                                                    checkout.confirm.url(),
                                            },
                                        })}
                                        class={props.class}
                                    >
                                        Zum Login
                                    </Link>
                                {/snippet}
                            </Button>
                            <p class="mt-3 text-sm text-gray-500">
                                Für den Bestellabschluss ist aktuell ein
                                Kundenkonto erforderlich.
                            </p>
                        {/if}
                    </div>

                    <p class="mt-3 text-center text-xs text-gray-400">
                        * Preise inkl. MwSt. zzgl. Versandkosten
                    </p>
                </div>
            </div>
        </div>
    </main>

    <AppFooter />
</div>
