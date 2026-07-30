<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Adressen', href: '/settings/addresses' }],
    };
</script>

<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        Select,
        SelectContent,
        SelectItem,
        SelectTrigger,
    } from '@/components/ui/select';
    import type { AddressData } from '@/types/cart';

    const ADDRESSES_URL = '/settings/addresses';

    const EMPTY_ADDRESS: AddressData = {
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

    let {
        shipping,
        billing,
    }: {
        shipping: AddressData | null;
        billing: AddressData | null;
    } = $props();

    // svelte-ignore state_referenced_locally
    const form = useForm({
        billing_same_as_shipping: billing === null,
        shipping: { ...EMPTY_ADDRESS, ...(shipping ?? {}) },
        billing: { ...EMPTY_ADDRESS, ...(billing ?? {}) },
    });

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.put(ADDRESSES_URL, { preserveScroll: true });
    }
</script>

<AppHead title="Adressen — Einstellungen" />

<div class="flex flex-col space-y-6">
    <Heading
        variant="small"
        title="Adressen"
        description="Deine Standard-Liefer- und Rechnungsadresse"
    />

    <form onsubmit={submit} class="flex flex-col gap-8">
        <!-- Lieferadresse -->
        <div class="flex flex-col gap-4">
            <p class="text-sm font-semibold">Lieferadresse</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <Label for="s_company">Firma (optional)</Label>
                    <Input
                        id="s_company"
                        bind:value={form.shipping.company}
                        placeholder="Firmenname"
                    />
                </div>

                <div>
                    <Label for="s_salutation">Anrede</Label>
                    <Select
                        value={form.shipping.salutation}
                        onValueChange={(v) => (form.shipping.salutation = v)}
                    >
                        <SelectTrigger id="s_salutation" class="w-full">
                            <div data-slot="select-value">
                                {form.shipping.salutation || '—'}
                            </div>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">—</SelectItem>
                            <SelectItem value="Herr">Herr</SelectItem>
                            <SelectItem value="Frau">Frau</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div></div>

                <div>
                    <Label for="s_first_name"
                        >Vorname <span class="text-red-500">*</span></Label
                    >
                    <Input
                        id="s_first_name"
                        bind:value={form.shipping.first_name}
                    />
                    {#if form.errors['shipping.first_name']}<p
                            class="mt-1 text-xs text-red-500"
                        >
                            {form.errors['shipping.first_name']}
                        </p>{/if}
                </div>
                <div>
                    <Label for="s_last_name"
                        >Nachname <span class="text-red-500">*</span></Label
                    >
                    <Input
                        id="s_last_name"
                        bind:value={form.shipping.last_name}
                    />
                    {#if form.errors['shipping.last_name']}<p
                            class="mt-1 text-xs text-red-500"
                        >
                            {form.errors['shipping.last_name']}
                        </p>{/if}
                </div>

                <div>
                    <Label for="s_street"
                        >Straße <span class="text-red-500">*</span></Label
                    >
                    <Input id="s_street" bind:value={form.shipping.street} />
                    {#if form.errors['shipping.street']}<p
                            class="mt-1 text-xs text-red-500"
                        >
                            {form.errors['shipping.street']}
                        </p>{/if}
                </div>
                <div>
                    <Label for="s_house_number"
                        >Hausnr. <span class="text-red-500">*</span></Label
                    >
                    <Input
                        id="s_house_number"
                        bind:value={form.shipping.house_number}
                    />
                    {#if form.errors['shipping.house_number']}<p
                            class="mt-1 text-xs text-red-500"
                        >
                            {form.errors['shipping.house_number']}
                        </p>{/if}
                </div>

                <div class="sm:col-span-2">
                    <Label for="s_address_line2">Adresszusatz (optional)</Label>
                    <Input
                        id="s_address_line2"
                        bind:value={form.shipping.address_line2}
                        placeholder="c/o, Gebäude, Etage"
                    />
                </div>

                <div>
                    <Label for="s_zip"
                        >PLZ <span class="text-red-500">*</span></Label
                    >
                    <Input id="s_zip" bind:value={form.shipping.zip} />
                    {#if form.errors['shipping.zip']}<p
                            class="mt-1 text-xs text-red-500"
                        >
                            {form.errors['shipping.zip']}
                        </p>{/if}
                </div>
                <div>
                    <Label for="s_city"
                        >Ort <span class="text-red-500">*</span></Label
                    >
                    <Input id="s_city" bind:value={form.shipping.city} />
                    {#if form.errors['shipping.city']}<p
                            class="mt-1 text-xs text-red-500"
                        >
                            {form.errors['shipping.city']}
                        </p>{/if}
                </div>

                <div>
                    <Label for="s_country">Land</Label>
                    <Select
                        value={form.shipping.country}
                        onValueChange={(v) => (form.shipping.country = v)}
                    >
                        <SelectTrigger id="s_country" class="w-full">
                            <div data-slot="select-value">
                                {#if form.shipping.country === 'DE'}Deutschland{:else if form.shipping.country === 'AT'}Österreich{:else if form.shipping.country === 'CH'}Schweiz{:else if form.shipping.country === 'NL'}Niederlande{:else if form.shipping.country === 'BE'}Belgien{:else if form.shipping.country === 'LU'}Luxemburg{:else if form.shipping.country === 'FR'}Frankreich{:else}Deutschland{/if}
                            </div>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="DE">Deutschland</SelectItem>
                            <SelectItem value="AT">Österreich</SelectItem>
                            <SelectItem value="CH">Schweiz</SelectItem>
                            <SelectItem value="NL">Niederlande</SelectItem>
                            <SelectItem value="BE">Belgien</SelectItem>
                            <SelectItem value="LU">Luxemburg</SelectItem>
                            <SelectItem value="FR">Frankreich</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div>
                    <Label for="s_phone">Telefon (optional)</Label>
                    <Input
                        id="s_phone"
                        bind:value={form.shipping.phone}
                        placeholder="+49 176 12345678"
                    />
                </div>
            </div>
        </div>

        <!-- Rechnungsadresse -->
        <div class="flex flex-col gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
                <Checkbox bind:checked={form.billing_same_as_shipping} />
                <span class="text-sm font-medium"
                    >Rechnungsadresse entspricht der Lieferadresse</span
                >
            </label>

            {#if !form.billing_same_as_shipping}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <Label for="b_company">Firma (optional)</Label>
                        <Input
                            id="b_company"
                            bind:value={form.billing.company}
                            placeholder="Firmenname"
                        />
                    </div>

                    <div>
                        <Label for="b_salutation">Anrede</Label>
                        <Select
                            value={form.billing.salutation}
                            onValueChange={(v) => (form.billing.salutation = v)}
                        >
                            <SelectTrigger id="b_salutation" class="w-full">
                                <div data-slot="select-value">
                                    {form.billing.salutation || '—'}
                                </div>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">—</SelectItem>
                                <SelectItem value="Herr">Herr</SelectItem>
                                <SelectItem value="Frau">Frau</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div></div>

                    <div>
                        <Label for="b_first_name">Vorname</Label>
                        <Input
                            id="b_first_name"
                            bind:value={form.billing.first_name}
                        />
                    </div>
                    <div>
                        <Label for="b_last_name">Nachname</Label>
                        <Input
                            id="b_last_name"
                            bind:value={form.billing.last_name}
                        />
                    </div>

                    <div>
                        <Label for="b_street">Straße</Label>
                        <Input id="b_street" bind:value={form.billing.street} />
                    </div>
                    <div>
                        <Label for="b_house_number">Hausnr.</Label>
                        <Input
                            id="b_house_number"
                            bind:value={form.billing.house_number}
                        />
                    </div>

                    <div class="sm:col-span-2">
                        <Label for="b_address_line2"
                            >Adresszusatz (optional)</Label
                        >
                        <Input
                            id="b_address_line2"
                            bind:value={form.billing.address_line2}
                            placeholder="c/o, Gebäude, Etage"
                        />
                    </div>

                    <div>
                        <Label for="b_zip">PLZ</Label>
                        <Input id="b_zip" bind:value={form.billing.zip} />
                    </div>
                    <div>
                        <Label for="b_city">Ort</Label>
                        <Input id="b_city" bind:value={form.billing.city} />
                    </div>

                    <div>
                        <Label for="b_country">Land</Label>
                        <Select
                            value={form.billing.country}
                            onValueChange={(v) => (form.billing.country = v)}
                        >
                            <SelectTrigger id="b_country" class="w-full">
                                <div data-slot="select-value">
                                    {#if form.billing.country === 'DE'}Deutschland{:else if form.billing.country === 'AT'}Österreich{:else if form.billing.country === 'CH'}Schweiz{:else if form.billing.country === 'NL'}Niederlande{:else if form.billing.country === 'BE'}Belgien{:else if form.billing.country === 'LU'}Luxemburg{:else if form.billing.country === 'FR'}Frankreich{:else}Deutschland{/if}
                                </div>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="DE">Deutschland</SelectItem>
                                <SelectItem value="AT">Österreich</SelectItem>
                                <SelectItem value="CH">Schweiz</SelectItem>
                                <SelectItem value="NL">Niederlande</SelectItem>
                                <SelectItem value="BE">Belgien</SelectItem>
                                <SelectItem value="LU">Luxemburg</SelectItem>
                                <SelectItem value="FR">Frankreich</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label for="b_phone">Telefon (optional)</Label>
                        <Input
                            id="b_phone"
                            bind:value={form.billing.phone}
                            placeholder="+49 176 12345678"
                        />
                    </div>
                </div>
            {/if}
        </div>

        <div class="flex items-center gap-4">
            <Button type="submit" disabled={form.processing}>
                {form.processing ? 'Speichern…' : 'Speichern'}
            </Button>
        </div>
    </form>
</div>
