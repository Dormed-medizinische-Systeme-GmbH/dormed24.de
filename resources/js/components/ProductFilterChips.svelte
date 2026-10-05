<script lang="ts">
    import X from '@lucide/svelte/icons/x';
    import { clearFilters, visitWithFilters } from '@/lib/productFilters';
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

    const activeManufacturers = $derived(
        filterOptions.manufacturers.filter((option) =>
            filters.manufacturers.includes(option.id),
        ),
    );

    const activeSpecialties = $derived(
        filterOptions.specialties.filter((option) =>
            filters.specialties.includes(option.id),
        ),
    );

    const chips = $derived([
        ...activeManufacturers.map((option) => ({
            key: `manufacturer-${option.id}`,
            name: option.name,
            remove: () =>
                visitWithFilters({
                    ...filters,
                    manufacturers: filters.manufacturers.filter(
                        (value) => value !== option.id,
                    ),
                }),
        })),
        ...activeSpecialties.map((option) => ({
            key: `specialty-${option.id}`,
            name: option.name,
            remove: () =>
                visitWithFilters({
                    ...filters,
                    specialties: filters.specialties.filter(
                        (value) => value !== option.id,
                    ),
                }),
        })),
    ]);
</script>

{#if chips.length > 0}
    <div class="mb-5 flex flex-wrap items-center gap-2">
        {#each chips as chip (chip.key)}
            <button
                type="button"
                onclick={chip.remove}
                aria-label={`Filter ${chip.name} entfernen`}
                class="inline-flex items-center gap-1.5 rounded-full border border-border bg-white py-1 pl-3 pr-2 text-sm text-foreground shadow-sm transition-colors hover:border-brand-accent hover:text-brand-accent"
            >
                {chip.name}
                <X class="size-3.5" />
            </button>
        {/each}
        <button
            type="button"
            onclick={clearFilters}
            class="px-2 text-sm font-medium text-brand-accent hover:underline"
        >
            Alle zurücksetzen
        </button>
    </div>
{/if}
