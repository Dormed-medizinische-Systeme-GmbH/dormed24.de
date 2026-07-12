<script lang="ts">
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        Select,
        SelectContent,
        SelectItem,
        SelectTrigger,
    } from '@/components/ui/select';
    import type { AddressData } from '@/types/cart';

    export type AddressUpdate = {
        prefix: string;
        key: keyof AddressData;
        value: string;
    };

    let {
        data,
        errors = {},
        prefix,
        legend,
        onUpdate,
    }: {
        data: AddressData;
        errors?: Record<string, string>;
        prefix: string;
        legend: string;
        onUpdate?: (update: AddressUpdate) => void;
    } = $props();

    function update(key: keyof AddressData, value: string) {
        onUpdate?.({ prefix, key, value });
    }
</script>

<div class="space-y-4">
    <h3 class="font-bold text-foreground">{legend}</h3>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <Label for="{prefix}.salutation">Anrede</Label>
            <Select
                value={data.salutation}
                onValueChange={(v: string) => {
                    update('salutation', v);

                    // Firmenfeld nur bei Anrede "Firma" — beim Wechsel weg
                    // davon den alten Firmennamen nicht stehen lassen.
                    if (v !== 'Firma' && data.company) {
                        update('company', '');
                    }
                }}
            >
                <SelectTrigger id="{prefix}.salutation" class="w-full">
                    {#if data.salutation}
                        <div data-slot="select-value">{data.salutation}</div>
                    {:else}
                        <div
                            data-slot="select-value"
                            class="text-muted-foreground"
                        >
                            —
                        </div>
                    {/if}
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">—</SelectItem>
                    <SelectItem value="Herr">Herr</SelectItem>
                    <SelectItem value="Frau">Frau</SelectItem>
                    <SelectItem value="Firma">Firma</SelectItem>
                </SelectContent>
            </Select>
        </div>

        {#if data.salutation === 'Firma'}
            <div>
                <Label for="{prefix}.company">Firma</Label>
                <Input
                    id="{prefix}.company"
                    value={data.company}
                    oninput={(e) => update('company', e.currentTarget.value)}
                    placeholder="Firmenname"
                    class={errors[`${prefix}.company`]
                        ? 'border-destructive'
                        : ''}
                />
                {#if errors[`${prefix}.company`]}
                    <p class="mt-1 text-xs text-destructive">
                        {errors[`${prefix}.company`]}
                    </p>
                {/if}
            </div>
        {:else}
            <div></div>
        {/if}

        <div>
            <Label for="{prefix}.first_name">
                Vorname <span class="text-destructive">*</span>
            </Label>
            <Input
                id="{prefix}.first_name"
                value={data.first_name}
                oninput={(e) => update('first_name', e.currentTarget.value)}
                class={errors[`${prefix}.first_name`]
                    ? 'border-destructive'
                    : ''}
            />
            {#if errors[`${prefix}.first_name`]}
                <p class="mt-1 text-xs text-destructive">
                    {errors[`${prefix}.first_name`]}
                </p>
            {/if}
        </div>

        <div>
            <Label for="{prefix}.last_name">
                Nachname <span class="text-destructive">*</span>
            </Label>
            <Input
                id="{prefix}.last_name"
                value={data.last_name}
                oninput={(e) => update('last_name', e.currentTarget.value)}
                class={errors[`${prefix}.last_name`]
                    ? 'border-destructive'
                    : ''}
            />
            {#if errors[`${prefix}.last_name`]}
                <p class="mt-1 text-xs text-destructive">
                    {errors[`${prefix}.last_name`]}
                </p>
            {/if}
        </div>

        <div class="sm:col-span-1">
            <Label for="{prefix}.street">
                Straße <span class="text-destructive">*</span>
            </Label>
            <Input
                id="{prefix}.street"
                value={data.street}
                oninput={(e) => update('street', e.currentTarget.value)}
                class={errors[`${prefix}.street`] ? 'border-destructive' : ''}
            />
            {#if errors[`${prefix}.street`]}
                <p class="mt-1 text-xs text-destructive">
                    {errors[`${prefix}.street`]}
                </p>
            {/if}
        </div>

        <div>
            <Label for="{prefix}.house_number">
                Hausnr. <span class="text-destructive">*</span>
            </Label>
            <Input
                id="{prefix}.house_number"
                value={data.house_number}
                oninput={(e) => update('house_number', e.currentTarget.value)}
                class={errors[`${prefix}.house_number`]
                    ? 'border-destructive'
                    : ''}
            />
            {#if errors[`${prefix}.house_number`]}
                <p class="mt-1 text-xs text-destructive">
                    {errors[`${prefix}.house_number`]}
                </p>
            {/if}
        </div>

        <div class="sm:col-span-2">
            <Label for="{prefix}.address_line2">Adresszusatz (optional)</Label>
            <Input
                id="{prefix}.address_line2"
                value={data.address_line2}
                oninput={(e) => update('address_line2', e.currentTarget.value)}
                placeholder="z.B. c/o, Gebäude, Etage"
            />
        </div>

        <!-- PLZ + Ort teilen sich einen Slot (PLZ schmal, Ort Rest) -->
        <div>
            <div class="flex gap-2">
                <div class="w-24 shrink-0">
                    <Label for="{prefix}.zip">
                        PLZ <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="{prefix}.zip"
                        value={data.zip}
                        oninput={(e) => update('zip', e.currentTarget.value)}
                        class={errors[`${prefix}.zip`]
                            ? 'border-destructive'
                            : ''}
                    />
                </div>

                <div class="min-w-0 flex-1">
                    <Label for="{prefix}.city">
                        Ort <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="{prefix}.city"
                        value={data.city}
                        oninput={(e) => update('city', e.currentTarget.value)}
                        class={errors[`${prefix}.city`]
                            ? 'border-destructive'
                            : ''}
                    />
                </div>
            </div>

            {#if errors[`${prefix}.zip`] || errors[`${prefix}.city`]}
                <p class="mt-1 text-xs text-destructive">
                    {errors[`${prefix}.zip`] ?? errors[`${prefix}.city`]}
                </p>
            {/if}
        </div>

        <div class="sm:col-span-1">
            <Label for="{prefix}.country">Land</Label>
            <Select
                value={data.country}
                onValueChange={(v: string) => update('country', v)}
            >
                <SelectTrigger id="{prefix}.country" class="w-full">
                    <div data-slot="select-value">
                        {#if data.country === 'DE'}Deutschland{:else if data.country === 'AT'}Österreich{:else if data.country === 'CH'}Schweiz{:else if data.country === 'NL'}Niederlande{:else if data.country === 'BE'}Belgien{:else if data.country === 'LU'}Luxemburg{:else if data.country === 'FR'}Frankreich{:else}Deutschland{/if}
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
    </div>
</div>
