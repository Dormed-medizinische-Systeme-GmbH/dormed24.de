<script lang="ts">
    import X from '@lucide/svelte/icons/x';
    import { visitWithManufacturerFilter } from '@/lib/productFilters';
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

    function removeManufacturer(id: number) {
        visitWithManufacturerFilter(
            filters.manufacturers.filter((value) => value !== id),
        );
    }
</script>

{#if activeManufacturers.length > 0}
    <div class="mb-5 flex flex-wrap items-center gap-2">
        {#each activeManufacturers as manufacturer (manufacturer.id)}
            <button
                type="button"
                onclick={() => removeManufacturer(manufacturer.id)}
                aria-label={`Filter ${manufacturer.name} entfernen`}
                class="inline-flex items-center gap-1.5 rounded-full border border-border bg-white py-1 pl-3 pr-2 text-sm text-foreground shadow-sm transition-colors hover:border-brand-accent hover:text-brand-accent"
            >
                {manufacturer.name}
                <X class="size-3.5" />
            </button>
        {/each}
        <button
            type="button"
            onclick={() => visitWithManufacturerFilter([])}
            class="px-2 text-sm font-medium text-brand-accent hover:underline"
        >
            Alle zurücksetzen
        </button>
    </div>
{/if}
