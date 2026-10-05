<script lang="ts">
    import SlidersHorizontal from '@lucide/svelte/icons/sliders-horizontal';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
    import {
        countForManufacturers,
        visitWithManufacturerFilter,
    } from '@/lib/productFilters';
    import type {
        ProductListFilterOptions,
        ProductListFilters,
    } from '@/lib/productFilters';

    let {
        filters,
        filterOptions,
    }: {
        filters: ProductListFilters;
        filterOptions: ProductListFilterOptions;
    } = $props();

    let open = $state(false);
    // Auswahl im Sheet wird erst mit "anzeigen" angewendet.
    let pendingManufacturers = $state<number[]>([]);

    const activeFilterCount = $derived(
        filters.manufacturers.length + filters.specialties.length,
    );
    const pendingCount = $derived(
        countForManufacturers(filterOptions, pendingManufacturers),
    );

    function openSheet() {
        pendingManufacturers = [...filters.manufacturers];
        open = true;
    }

    function toggleManufacturer(id: number, checked: boolean) {
        pendingManufacturers = checked
            ? [...pendingManufacturers, id]
            : pendingManufacturers.filter((value) => value !== id);
    }

    function applyFilters() {
        open = false;
        visitWithManufacturerFilter(pendingManufacturers);
    }

    function resetFilters() {
        pendingManufacturers = [];

        if (filters.manufacturers.length > 0) {
            applyFilters();
        }
    }
</script>

<button
    type="button"
    aria-haspopup="dialog"
    onclick={openSheet}
    class="inline-flex h-[38px] shrink-0 items-center gap-2 self-start rounded border border-border bg-white px-3 text-sm font-semibold text-brand-secondary shadow-sm transition hover:bg-muted sm:self-auto"
>
    <SlidersHorizontal class="size-4" />
    Filter
    {#if activeFilterCount > 0}
        <span
            class="grid size-5 place-items-center rounded-full bg-brand-accent text-[11px] font-bold text-white"
        >
            {activeFilterCount}
        </span>
    {/if}
</button>

<Sheet bind:open>
    <SheetContent
        side="right"
        class="w-[min(22rem,88vw)] gap-0 overflow-hidden border-l p-0 sm:max-w-none"
    >
        <div class="flex items-center gap-2 border-b px-5 py-4 pr-12">
            <SlidersHorizontal class="size-4" />
            <SheetTitle class="text-base">Filter</SheetTitle>
        </div>

        <div class="flex flex-1 flex-col gap-5 overflow-y-auto px-5 py-5">
            <fieldset class="border-b border-border pb-5">
                <legend class="mb-3 text-sm font-semibold text-foreground">
                    Hersteller
                </legend>
                {#if filterOptions.manufacturers.length === 0}
                    <p class="text-sm text-muted-foreground">
                        Keine Hersteller in dieser Auswahl.
                    </p>
                {:else}
                    <div class="flex flex-col gap-2.5">
                        {#each filterOptions.manufacturers as option (option.id)}
                            {@const checked = pendingManufacturers.includes(
                                option.id,
                            )}
                            <label
                                for={`filter-manufacturer-${option.id}`}
                                class="flex cursor-pointer items-center gap-2.5 text-sm text-muted-foreground hover:text-foreground"
                            >
                                <Checkbox
                                    id={`filter-manufacturer-${option.id}`}
                                    {checked}
                                    class="rounded"
                                    onCheckedChange={(value) =>
                                        toggleManufacturer(option.id, value)}
                                />
                                <span
                                    class="min-w-0 flex-1 {checked
                                        ? 'font-medium text-foreground'
                                        : ''}"
                                >
                                    {option.name}
                                </span>
                                <span
                                    class="shrink-0 text-xs tabular-nums text-muted-foreground"
                                >
                                    {option.count}
                                </span>
                            </label>
                        {/each}
                    </div>
                {/if}
            </fieldset>

            <!--
                Fachrichtungen: bewusst "tot" - das Datenmodell kennt sie noch
                nicht, das Backend liefert eine leere Liste. Sobald es eine
                Zuordnung Produkt <-> Fachrichtung gibt, hier wie bei den
                Herstellern rendern und in ProductFilters verdrahten.
            -->
            <fieldset>
                <legend class="mb-3 text-sm font-semibold text-foreground">
                    Fachrichtungen
                </legend>
                {#if filterOptions.specialties.length === 0}
                    <p class="text-sm text-muted-foreground">Folgt in Kürze.</p>
                {:else}
                    <div class="flex flex-col gap-2.5">
                        {#each filterOptions.specialties as option (option.id)}
                            <label
                                class="flex cursor-not-allowed items-center gap-2.5 text-sm text-muted-foreground/60"
                            >
                                <Checkbox disabled class="rounded" />
                                <span class="min-w-0 flex-1">{option.name}</span
                                >
                                <span class="shrink-0 text-xs tabular-nums">
                                    {option.count}
                                </span>
                            </label>
                        {/each}
                    </div>
                {/if}
            </fieldset>
        </div>

        <div class="flex flex-col gap-2 border-t p-4">
            <button
                type="button"
                onclick={resetFilters}
                class="h-10 w-full rounded-lg border px-4 text-sm font-semibold text-brand-secondary transition hover:bg-muted"
            >
                Zurücksetzen
            </button>
            <button
                type="button"
                onclick={applyFilters}
                class="h-10 w-full rounded-lg bg-primary px-4 text-sm font-semibold text-white transition hover:bg-primary/90"
            >
                {pendingCount} anzeigen
            </button>
        </div>
    </SheetContent>
</Sheet>
