<script lang="ts">
    // UX-EXPERIMENT: Kunden-Settings als Modal (Alternative zu den
    // /settings-Seiten). Entweder dieses Modal oder die Seiten fliegen
    // nach der Bewertung wieder raus.
    import { Link, page, router } from '@inertiajs/svelte';
    import ArrowLeft from '@lucide/svelte/icons/arrow-left';
    import LogOut from '@lucide/svelte/icons/log-out';
    import MapPin from '@lucide/svelte/icons/map-pin';
    import Package from '@lucide/svelte/icons/package';
    import Shield from '@lucide/svelte/icons/shield';
    import User from '@lucide/svelte/icons/user';
    import UserCog from '@lucide/svelte/icons/user-cog';
    import X from '@lucide/svelte/icons/x';
    import AddressForm from '@/components/AddressForm.svelte';
    import Picture from '@/components/Picture.svelte';
    import RateProductButton from '@/components/RateProductButton.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Separator } from '@/components/ui/separator';
    import { Skeleton } from '@/components/ui/skeleton';
    import { formatPrice } from '@/lib/currency';
    import { fetchJson } from '@/lib/http';
    import { orderStatusBadgeClass, orderStatusLabel } from '@/lib/status';
    import { logout } from '@/routes';
    import {
        edit as addressesEdit,
        update as addressesUpdate,
    } from '@/routes/addresses';
    import customerRoutes from '@/routes/customer';
    import { update as profileUpdate } from '@/routes/profile';
    import { edit as securityEdit } from '@/routes/security';
    import type { AddressData, Customer } from '@/types';

    type Pane = 'orders' | 'addresses' | 'profile' | 'security';

    type OrderRow = {
        id: number;
        status: string;
        total_amount: string;
        created_at: string;
    };

    type AddressSnapshot = Record<string, string> | null;

    type OrderDetail = {
        id: number;
        status: string;
        payment_method_label: string | null;
        created_at: string | null;
        shipping_address: AddressSnapshot;
        billing_address: AddressSnapshot;
        items: {
            id: number;
            product_id: number | null;
            product_name: string;
            unit_price: string;
            quantity: number;
            image_url: string | null;
            rating: 'rateable' | 'rated' | null;
        }[];
        summary: {
            subtotal: string;
            shipping_method: string | null;
            shipping_total: string;
            vat_rate: number;
            vat_amount: string;
            total: string;
        };
    };

    const auth = $derived(page.props.auth);
    const customer = $derived(auth?.user as Customer | undefined);

    let open = $state(false);
    let active = $state<Pane>('orders');

    const panes: { id: Pane; title: string; icon: typeof Package }[] = [
        { id: 'profile', title: 'Profil', icon: User },
        { id: 'addresses', title: 'Adressen', icon: MapPin },
        { id: 'orders', title: 'Bestellungen', icon: Package },
        { id: 'security', title: 'Sicherheit', icon: Shield },
    ];

    // --- Bestellungen ---
    let orders = $state<OrderRow[] | null>(null);

    // --- Bestelldetail (vollflächig im Modal, ohne Sidebar) ---
    let selectedOrder = $state<OrderDetail | null>(null);
    let loadingDetail = $state(false);

    const detailOpen = $derived(loadingDetail || selectedOrder !== null);

    async function openOrderDetail(id: number) {
        loadingDetail = true;

        const { ok, data } = await fetchJson<{ order: OrderDetail }>(
            `/customer/orders/${id}`,
        );

        selectedOrder = ok ? data.order : null;
        loadingDetail = false;
    }

    function closeOrderDetail() {
        selectedOrder = null;
        loadingDetail = false;
    }

    function orderCode(id: number): string {
        return `ORD-${String(id).padStart(5, '0')}`;
    }

    function formatAddress(address: AddressSnapshot): string[] {
        if (!address) {
            return ['-'];
        }

        const fullName = [
            address.salutation,
            address.first_name,
            address.last_name,
        ]
            .filter(Boolean)
            .join(' ')
            .trim();

        return [
            address.company ?? '',
            fullName,
            [address.street, address.house_number]
                .filter(Boolean)
                .join(' ')
                .trim(),
            address.address_line2 ?? '',
            [address.zip, address.city].filter(Boolean).join(' ').trim(),
            address.country ?? '',
        ].filter((line) => line !== '');
    }

    // --- Adressen ---
    function emptyAddress(): AddressData {
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

    let addressesLoaded = $state(false);
    let shippingAddress = $state<AddressData>(emptyAddress());
    let billingAddress = $state<AddressData>(emptyAddress());
    let billingSame = $state(true);
    let addressErrors = $state<Record<string, string>>({});
    let savingAddresses = $state(false);

    // --- Profil ---
    let profileName = $state('');
    let profileEmail = $state('');
    let profileErrors = $state<Record<string, string>>({});
    let savingProfile = $state(false);
    let profileInitialized = $state(false);

    $effect(() => {
        if (!open) {
            closeOrderDetail();

            return;
        }

        if (orders === null) {
            void loadOrders();
        }

        if (!addressesLoaded) {
            void loadAddresses();
        }

        if (!profileInitialized && customer) {
            profileName = customer.name;
            profileEmail = customer.email;
            profileInitialized = true;
        }
    });

    async function loadOrders() {
        const { ok, data } = await fetchJson<{ orders: OrderRow[] }>(
            customerRoutes.orders.url(),
        );

        orders = ok ? data.orders : [];
    }

    async function loadAddresses() {
        const { ok, data } = await fetchJson<{
            shipping: AddressData | null;
            billing: AddressData | null;
        }>(addressesEdit.url());

        if (ok) {
            shippingAddress = { ...emptyAddress(), ...(data.shipping ?? {}) };
            billingAddress = { ...emptyAddress(), ...(data.billing ?? {}) };
            billingSame = data.billing === null;
        }

        addressesLoaded = true;
    }

    function handleAddressUpdate(update: {
        prefix: string;
        key: string;
        value: string;
    }) {
        const { prefix, key, value } = update;

        if (prefix === 'shipping') {
            shippingAddress = { ...shippingAddress, [key]: value };
        } else if (prefix === 'billing') {
            billingAddress = { ...billingAddress, [key]: value };
        }

        const errorKey = `${prefix}.${key}`;

        if (addressErrors[errorKey]) {
            const next = { ...addressErrors };
            delete next[errorKey];
            addressErrors = next;
        }
    }

    function saveAddresses() {
        savingAddresses = true;
        addressErrors = {};

        router.put(
            addressesUpdate.url(),
            {
                shipping: shippingAddress,
                billing_same_as_shipping: billingSame,
                ...(billingSame ? {} : { billing: billingAddress }),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) => {
                    addressErrors = errors as Record<string, string>;
                },
                onFinish: () => {
                    savingAddresses = false;
                },
            },
        );
    }

    function saveProfile() {
        savingProfile = true;
        profileErrors = {};

        router.patch(
            profileUpdate.url(),
            { name: profileName, email: profileEmail },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) => {
                    profileErrors = errors as Record<string, string>;
                },
                onFinish: () => {
                    savingProfile = false;
                },
            },
        );
    }

    function goToSecurityPage() {
        open = false;
        router.visit(securityEdit.url());
    }

    function formatDate(value: string): string {
        return new Date(value).toLocaleDateString('de-DE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    }

    const activeTitle = $derived(
        panes.find((pane) => pane.id === active)?.title ?? '',
    );
</script>

{#if customer}
    <Button
        variant="ghost"
        size="icon"
        onclick={() => (open = true)}
        aria-label="Konto-Einstellungen (Modal)"
    >
        <UserCog class="size-5" />
    </Button>

    <Dialog bind:open>
        <DialogContent
            class="overflow-hidden p-0 md:max-h-[540px] md:max-w-[720px] lg:max-w-[840px]"
        >
            <DialogTitle class="sr-only">Konto-Einstellungen</DialogTitle>
            <DialogDescription class="sr-only">
                Bestellungen, Adressen und Profil verwalten.
            </DialogDescription>

            <div class="flex h-[520px] items-stretch">
                {#if !detailOpen}
                    <aside
                        class="hidden w-48 shrink-0 flex-col gap-1 border-r bg-muted/30 p-3 md:flex"
                    >
                        {#each panes as pane (pane.id)}
                            <Button
                                variant="ghost"
                                class="w-full justify-start {active === pane.id
                                    ? 'bg-muted'
                                    : ''}"
                                onclick={() => (active = pane.id)}
                            >
                                <pane.icon class="mr-2 size-4" />
                                {pane.title}
                            </Button>
                        {/each}

                        <Separator class="mb-2 mt-auto" />

                        <Button
                            variant="ghost"
                            class="w-full justify-start text-muted-foreground hover:text-destructive"
                            asChild
                        >
                            {#snippet children(props)}
                                <Link
                                    href={logout()}
                                    as="button"
                                    class={props.class}
                                    onclick={() => router.flushAll()}
                                >
                                    <LogOut class="mr-2 size-4" />
                                    Abmelden
                                </Link>
                            {/snippet}
                        </Button>
                    </aside>
                {/if}

                <main class="flex min-w-0 flex-1 flex-col">
                    <header
                        class="flex h-14 shrink-0 items-center gap-3 border-b px-5"
                    >
                        {#if detailOpen}
                            <Button
                                variant="ghost"
                                size="icon"
                                onclick={closeOrderDetail}
                                aria-label="Zurück zu Bestellungen"
                            >
                                <ArrowLeft class="size-4" />
                            </Button>
                            <h2 class="text-base font-semibold">
                                {selectedOrder
                                    ? `Bestellung ${orderCode(selectedOrder.id)}`
                                    : 'Bestellung'}
                            </h2>
                            <!-- UX-Vergleich: Badge zusätzlich im Kopf (Duplikat unten im Content) -->
                            {#if selectedOrder}
                                <span
                                    class={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${orderStatusBadgeClass(selectedOrder.status)}`}
                                >
                                    {orderStatusLabel(selectedOrder.status)}
                                </span>
                            {/if}
                        {:else}
                            <h2 class="text-base font-semibold">
                                {activeTitle}
                            </h2>
                        {/if}

                        <div class="ml-auto flex items-center gap-1">
                            {#if !detailOpen}
                                <!-- Mobile: Pane-Wechsel ohne Sidebar -->
                                <div class="flex gap-1 md:hidden">
                                    {#each panes as pane (pane.id)}
                                        <Button
                                            variant={active === pane.id
                                                ? 'secondary'
                                                : 'ghost'}
                                            size="icon"
                                            onclick={() => (active = pane.id)}
                                            aria-label={pane.title}
                                        >
                                            <pane.icon class="size-4" />
                                        </Button>
                                    {/each}
                                </div>
                            {/if}

                            <Button
                                variant="ghost"
                                size="icon"
                                onclick={() => (open = false)}
                                aria-label="Schließen"
                            >
                                <X class="size-4" />
                            </Button>
                        </div>
                    </header>

                    <div class="flex-1 overflow-y-auto p-5">
                        {#if detailOpen}
                            {#if loadingDetail || !selectedOrder}
                                <div class="flex flex-col gap-3">
                                    <Skeleton class="h-10 w-full" />
                                    <Skeleton class="h-24 w-full" />
                                    <Skeleton class="h-10 w-2/3" />
                                </div>
                            {:else}
                                <div class="flex flex-col gap-5">
                                    <div class="grid gap-4 sm:grid-cols-3">
                                        <div class="rounded-lg border p-4">
                                            <h3
                                                class="mb-2 text-sm font-semibold"
                                            >
                                                Lieferadresse
                                            </h3>
                                            <div
                                                class="space-y-0.5 text-sm text-muted-foreground"
                                            >
                                                {#each formatAddress(selectedOrder.shipping_address) as line (line)}
                                                    <p>{line}</p>
                                                {/each}
                                            </div>
                                        </div>
                                        <div class="rounded-lg border p-4">
                                            <h3
                                                class="mb-2 text-sm font-semibold"
                                            >
                                                Rechnungsadresse
                                            </h3>
                                            <div
                                                class="space-y-0.5 text-sm text-muted-foreground"
                                            >
                                                {#each formatAddress(selectedOrder.billing_address ?? selectedOrder.shipping_address) as line (line)}
                                                    <p>{line}</p>
                                                {/each}
                                            </div>
                                        </div>
                                        <!-- Allgemeine Info-Karte — hier lassen sich weitere Zeilen ergänzen -->
                                        <div class="rounded-lg border p-4">
                                            <h3
                                                class="mb-2 text-sm font-semibold"
                                            >
                                                Details
                                            </h3>
                                            <div
                                                class="space-y-0.5 text-sm text-muted-foreground"
                                            >
                                                {#if selectedOrder.created_at}
                                                    <p>
                                                        Bestellt am {formatDate(
                                                            selectedOrder.created_at,
                                                        )}
                                                    </p>
                                                {/if}
                                                <p>
                                                    Zahlung: {selectedOrder.payment_method_label ??
                                                        '-'}
                                                </p>
                                                <p>
                                                    Gesamt: {formatPrice(
                                                        selectedOrder.summary
                                                            .total,
                                                    )}*
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="overflow-hidden rounded-lg border"
                                    >
                                        {#each selectedOrder.items as item (item.id)}
                                            <div
                                                class="flex flex-wrap items-center gap-3 border-b px-4 py-3 last:border-b-0"
                                            >
                                                <div
                                                    class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded bg-gray-100"
                                                >
                                                    {#if item.image_url}
                                                        <Picture
                                                            src={item.image_url}
                                                            alt={item.product_name}
                                                            class="size-full object-cover object-center"
                                                        />
                                                    {:else}
                                                        <Package
                                                            class="size-4 text-gray-300"
                                                        />
                                                    {/if}
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <p
                                                        class="truncate text-sm font-medium"
                                                    >
                                                        {item.product_name}
                                                    </p>
                                                    <p
                                                        class="text-xs text-muted-foreground"
                                                    >
                                                        {item.quantity} × {formatPrice(
                                                            item.unit_price,
                                                        )}*
                                                    </p>
                                                </div>
                                                <span
                                                    class="text-sm font-semibold"
                                                >
                                                    {formatPrice(
                                                        (
                                                            Number(
                                                                item.unit_price,
                                                            ) * item.quantity
                                                        ).toFixed(2),
                                                    )}*
                                                </span>
                                                {#if item.product_id && item.rating}
                                                    <div class="w-full pl-13">
                                                        <RateProductButton
                                                            productId={item.product_id}
                                                            productName={item.product_name}
                                                            rating={item.rating}
                                                        />
                                                    </div>
                                                {/if}
                                            </div>
                                        {/each}
                                    </div>

                                    <div class="rounded-lg border p-4">
                                        <div class="space-y-1.5 text-sm">
                                            <div class="flex justify-between">
                                                <span
                                                    class="text-muted-foreground"
                                                    >Zwischensumme</span
                                                >
                                                <span
                                                    >{formatPrice(
                                                        selectedOrder.summary
                                                            .subtotal,
                                                    )}*</span
                                                >
                                            </div>
                                            <div class="flex justify-between">
                                                <span
                                                    class="text-muted-foreground"
                                                    >Versand{selectedOrder
                                                        .summary.shipping_method
                                                        ? ` – ${selectedOrder.summary.shipping_method}`
                                                        : ''}</span
                                                >
                                                <span
                                                    >{formatPrice(
                                                        selectedOrder.summary
                                                            .shipping_total,
                                                    )}*</span
                                                >
                                            </div>
                                            <div class="flex justify-between">
                                                <span
                                                    class="text-muted-foreground"
                                                    >MwSt. ({selectedOrder
                                                        .summary.vat_rate} %)</span
                                                >
                                                <span
                                                    >{formatPrice(
                                                        selectedOrder.summary
                                                            .vat_amount,
                                                    )}*</span
                                                >
                                            </div>
                                            <div
                                                class="mt-2 flex justify-between border-t pt-2 text-base font-semibold"
                                            >
                                                <span>Gesamt</span>
                                                <span
                                                    >{formatPrice(
                                                        selectedOrder.summary
                                                            .total,
                                                    )}*</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            {/if}
                        {:else if active === 'orders'}
                            {#if orders === null}
                                <div class="flex flex-col gap-3">
                                    <Skeleton class="h-10 w-full" />
                                    <Skeleton class="h-10 w-full" />
                                    <Skeleton class="h-10 w-2/3" />
                                </div>
                            {:else if orders.length === 0}
                                <p
                                    class="py-8 text-center text-sm text-muted-foreground"
                                >
                                    Noch keine Bestellungen vorhanden.
                                </p>
                            {:else}
                                <div class="flex flex-col divide-y">
                                    {#each orders as order (order.id)}
                                        <button
                                            class="flex items-center justify-between gap-3 py-3 text-left hover:bg-muted/40"
                                            onclick={() =>
                                                openOrderDetail(order.id)}
                                        >
                                            <span
                                                class="font-medium text-primary"
                                            >
                                                #{order.id}
                                            </span>
                                            <span
                                                class="text-sm text-muted-foreground"
                                            >
                                                {formatDate(order.created_at)}
                                            </span>
                                            <span
                                                class={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${orderStatusBadgeClass(order.status)}`}
                                            >
                                                {orderStatusLabel(order.status)}
                                            </span>
                                            <span class="text-sm font-semibold">
                                                {formatPrice(
                                                    order.total_amount,
                                                )}*
                                            </span>
                                        </button>
                                    {/each}
                                </div>
                            {/if}
                        {:else if active === 'addresses'}
                            {#if !addressesLoaded}
                                <div class="flex flex-col gap-3">
                                    <Skeleton class="h-10 w-full" />
                                    <Skeleton class="h-10 w-full" />
                                    <Skeleton class="h-10 w-2/3" />
                                </div>
                            {:else}
                                <div class="flex flex-col gap-6">
                                    <AddressForm
                                        data={shippingAddress}
                                        errors={addressErrors}
                                        prefix="shipping"
                                        legend="Lieferadresse"
                                        onUpdate={handleAddressUpdate}
                                    />

                                    <label
                                        class="flex cursor-pointer items-center gap-3"
                                    >
                                        <Checkbox bind:checked={billingSame} />
                                        <span class="text-sm">
                                            Rechnungsadresse ist identisch mit
                                            Lieferadresse
                                        </span>
                                    </label>

                                    {#if !billingSame}
                                        <AddressForm
                                            data={billingAddress}
                                            errors={addressErrors}
                                            prefix="billing"
                                            legend="Rechnungsadresse"
                                            onUpdate={handleAddressUpdate}
                                        />
                                    {/if}

                                    <div>
                                        <Button
                                            onclick={saveAddresses}
                                            disabled={savingAddresses}
                                        >
                                            {savingAddresses
                                                ? 'Speichern…'
                                                : 'Adressen speichern'}
                                        </Button>
                                    </div>
                                </div>
                            {/if}
                        {:else if active === 'profile'}
                            <div class="flex max-w-md flex-col gap-4">
                                <div class="flex flex-col gap-1.5">
                                    <Label for="modal_profile_name">Name</Label>
                                    <Input
                                        id="modal_profile_name"
                                        bind:value={profileName}
                                    />
                                    {#if profileErrors.name}
                                        <p class="text-xs text-red-500">
                                            {profileErrors.name}
                                        </p>
                                    {/if}
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <Label for="modal_profile_email"
                                        >E-Mail-Adresse</Label
                                    >
                                    <Input
                                        id="modal_profile_email"
                                        type="email"
                                        bind:value={profileEmail}
                                    />
                                    {#if profileErrors.email}
                                        <p class="text-xs text-red-500">
                                            {profileErrors.email}
                                        </p>
                                    {/if}
                                    <p class="text-xs text-muted-foreground">
                                        Nach einer Änderung muss die neue
                                        Adresse erneut bestätigt werden, bevor
                                        bestellt werden kann.
                                    </p>
                                </div>

                                <div>
                                    <Button
                                        onclick={saveProfile}
                                        disabled={savingProfile}
                                    >
                                        {savingProfile
                                            ? 'Speichern…'
                                            : 'Speichern'}
                                    </Button>
                                </div>
                            </div>
                        {:else if active === 'security'}
                            <div class="flex max-w-md flex-col gap-4">
                                <p class="text-sm text-muted-foreground">
                                    Passwort, Zwei-Faktor-Authentifizierung und
                                    Passkeys werden aus Sicherheitsgründen auf
                                    der vollständigen Einstellungsseite
                                    verwaltet (Passwortbestätigung
                                    erforderlich).
                                </p>
                                <div>
                                    <Button
                                        variant="outline"
                                        onclick={goToSecurityPage}
                                    >
                                        <Shield class="mr-2 size-4" />
                                        Sicherheitseinstellungen öffnen
                                    </Button>
                                </div>
                            </div>
                        {/if}
                    </div>
                </main>
            </div>
        </DialogContent>
    </Dialog>
{/if}
