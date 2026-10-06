<script lang="ts">
    import SlidersHorizontal from '@lucide/svelte/icons/sliders-horizontal';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
    import { countForSelection, visitWithFilters } from '@/lib/productFilters';
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
    let pendingSpecialties = $state<string[]>([]);

    const activeFilterCount = $derived(
        filters.manufacturers.length + filters.specialties.length,
    );
    const pendingCount = $derived(
        countForSelection(filterOptions, filters, {
            manufacturers: pendingManufacturers,
            specialties: pendingSpecialties,
        }),
    );

    function openSheet() {
        pendingManufacturers = [...filters.manufacturers];
        pendingSpecialties = [...filters.specialties];
        open = true;
    }

    function toggleManufacturer(id: number, checked: boolean) {
        pendingManufacturers = checked
            ? [...pendingManufacturers, id]
            : pendingManufacturers.filter((value) => value !== id);
    }

    function toggleSpecialty(slug: string, checked: boolean) {
        pendingSpecialties = checked
            ? [...pendingSpecialties, slug]
            : pendingSpecialties.filter((value) => value !== slug);
    }

    function applyFilters() {
        open = false;
        visitWithFilters({
            manufacturers: pendingManufacturers,
            specialties: pendingSpecialties,
        });
    }

    function resetFilters() {
        pendingManufacturers = [];
        pendingSpecialties = [];

        if (activeFilterCount > 0) {
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
            <fieldset
                class={filterOptions.specialties.length > 0
                    ? 'border-b border-border pb-5'
                    : ''}
            >
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

            <!-- Nur wo Produkte mit Fachrichtungen vorkommen (Ultraschallsysteme/-sonden). -->
            {#if filterOptions.specialties.length > 0}
                <fieldset>
                    <legend class="mb-3 text-sm font-semibold text-foreground">
                        Fachrichtungen
                    </legend>
                    <div class="flex flex-col gap-2.5">
                        {#each filterOptions.specialties as option (option.id)}
                            {@const checked = pendingSpecialties.includes(
                                option.id,
                            )}
                            <label
                                for={`filter-specialty-${option.id}`}
                                class="flex cursor-pointer items-center gap-2.5 text-sm text-muted-foreground hover:text-foreground"
                            >
                                <Checkbox
                                    id={`filter-specialty-${option.id}`}
                                    {checked}
                                    class="rounded"
                                    onCheckedChange={(value) =>
                                        toggleSpecialty(option.id, value)}
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
                </fieldset>
            {/if}
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
                {pendingCount === null
                    ? 'Ergebnisse anzeigen'
                    : `${pendingCount} anzeigen`}
            </button>
        </div>
    </SheetContent>
</Sheet>
