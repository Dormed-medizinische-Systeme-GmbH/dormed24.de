<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { index as magazineIndex } from '@/actions/App/Http/Controllers/MagazineController';
    import { cn } from '@/lib/utils';

    let {
        categories,
        activeCategory,
        orientation = 'row',
    }: {
        categories: Record<string, string>;
        activeCategory: string | null;
        orientation?: 'row' | 'column';
    } = $props();

    const filters = $derived([
        { key: null, label: 'Alle' },
        ...Object.entries(categories).map(([key, label]) => ({ key, label })),
    ]);

    function filterUrl(key: string | null): string {
        return magazineIndex.url(key ? { query: { kategorie: key } } : {});
    }
</script>

<nav
    aria-label="Kategorien"
    class={cn(
        'flex gap-2',
        orientation === 'row' ? 'flex-wrap' : 'flex-col gap-1',
    )}
>
    {#each filters as filter (filter.key ?? 'all')}
        <Link
            href={filterUrl(filter.key)}
            preserveScroll
            aria-current={filter.key === activeCategory ? 'page' : undefined}
            class={cn(
                'text-sm font-medium transition-colors',
                orientation === 'row'
                    ? 'rounded-full border px-4 py-2'
                    : 'rounded-md px-3 py-2',
                filter.key === activeCategory
                    ? 'border-brand-secondary bg-brand-secondary text-white'
                    : orientation === 'row'
                      ? 'border-gray-300 text-gray-700 hover:border-brand-accent'
                      : 'text-gray-700 hover:bg-slate-100',
            )}
        >
            {filter.label}
        </Link>
    {/each}
</nav>
