<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import CheckCircle from '@lucide/svelte/icons/circle-check-big';
    import Mail from '@lucide/svelte/icons/mail';
    import AppFooter from '@/components/AppFooter.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ShopHeader from '@/components/ShopHeader.svelte';
    import { Separator } from '@/components/ui/separator';
    import { formatPrice } from '@/lib/currency';

    type Item = {
        name: string;
        product_number: string | number;
        quantity: number;
        unit_price: string | number;
        line_total: string | number;
    };

    let {
        order_id,
        items,
        subtotal,
        shipping_total,
        vat_rate,
        vat_amount,
        total,
        customer_email,
    }: {
        order_id: number;
        items: Item[];
        subtotal: string | number;
        shipping_total: string | number;
        vat_rate: number;
        vat_amount: string | number;
        total: string | number;
        customer_email: string;
    } = $props();

    type ContactInfo = {
        email: string;
        phone: string;
        fax: string;
        phone_href: string;
        fax_href: string;
    };

    const contact = $derived(page.props.contact as ContactInfo);
</script>

<AppHead title="Bestellung bestätigt" />

<div class="flex min-h-screen flex-col bg-gray-50">
    <ShopHeader />

    <main class="flex-1 mx-auto max-w-3xl px-4 py-12 lg:px-8">
        <!-- Success header -->
        <div class="mb-10 flex flex-col items-center text-center">
            <div
                class="mb-5 flex size-16 items-center justify-center rounded-full bg-green-100"
            >
                <CheckCircle class="size-8 text-green-600" />
            </div>
            <h1 class="text-2xl font-bold text-gray-900">
                Vielen Dank für Ihre Bestellung!
            </h1>
            <p class="mt-2 text-sm text-gray-500">
                Bestellnummer <span class="font-semibold text-gray-700"
                    >#{order_id}</span
                >
            </p>
        </div>

        <!-- Order summary -->
        <div class="overflow-hidden rounded-xl border bg-white">
            <div class="border-b bg-gray-50 px-6 py-4">
                <h2 class="font-semibold text-gray-900">Bestellübersicht</h2>
            </div>

            <!-- Items -->
            <div class="divide-y">
                {#each items as item (item.product_number)}
                    <div class="flex items-center gap-4 px-6 py-4">
                        <div
                            class="flex size-12 shrink-0 items-center justify-center rounded border bg-gray-50"
                        >
                            <div class="size-7 rounded bg-gray-200"></div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p
                                class="truncate text-sm font-semibold text-gray-900"
                            >
                                {item.name}
                            </p>
                            <p class="text-xs text-gray-400">
                                Nr. {item.product_number}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900">
                                {formatPrice(item.line_total)}
                            </p>
                            <p class="text-xs text-gray-400">
                                {item.quantity} × {formatPrice(item.unit_price)}
                            </p>
                        </div>
                    </div>
                {/each}
            </div>

            <Separator />

            <!-- Totals -->
            <div class="space-y-2 px-6 py-4 text-sm">
                <div class="flex justify-between text-gray-600">
                    <span>Zwischensumme</span>
                    <span>{formatPrice(subtotal)}</span>
                </div>
                <div class="flex justify-between text-gray-600">
                    <span>Versandkosten</span>
                    <span>{formatPrice(shipping_total)}</span>
                </div>
                <div class="flex justify-between text-gray-500">
                    <span>zzgl. {vat_rate} % MwSt.</span>
                    <span>{formatPrice(vat_amount)}</span>
                </div>
                <Separator class="my-2" />
                <div
                    class="flex justify-between text-base font-bold text-gray-900"
                >
                    <span>Gesamt</span>
                    <span>{formatPrice(total)}</span>
                </div>
            </div>
        </div>

        <!-- Mail notice -->
        <div
            class="mb-6 flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 px-5 py-4"
        >
            <Mail class="mt-0.5 size-5 shrink-0 text-blue-600" />
            <p class="text-sm text-blue-800">
                Eine Bestellbestätigung wird in Kürze an
                <span class="font-semibold">{customer_email}</span> versendet.
            </p>
        </div>

        <!-- Actions
        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
            <Button
                variant="outline"
                class="flex-1 gap-2 opacity-50"
                disabled
            >
                <Download class="size-4" />
                Rechnung herunterladen
            </Button>
            <Button asChild class="flex-1 bg-primary text-white hover:bg-primary/90">
                {#snippet children(props)}
                    <Link href="/" class={props.class}>
                        Weiter einkaufen
                    </Link>
                {/snippet}
            </Button>
        </div> -->

        <p class="mt-6 text-center text-xs text-gray-400">
            Bei Fragen erreichen Sie uns unter <a
                href={contact.phone_href}
                class="hover:underline">{contact.phone}</a
            >
        </p>
    </main>

    <AppFooter />
</div>
