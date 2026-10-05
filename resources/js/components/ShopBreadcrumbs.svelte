<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import House from '@lucide/svelte/icons/house';
    import { cn } from '@/lib/utils';
    import { home } from '@/routes';

    /**
     * Breadcrumb der öffentlichen Shop-Seiten (Produktlisten + Produktansicht).
     * "Startseite" steht immer vorne; das letzte Element ist die aktuelle
     * Seite und wird ohne Link dargestellt.
     */
    type ShopBreadcrumbItem = { title: string; href?: string };

    let {
        items,
        class: className = '',
    }: {
        items: ShopBreadcrumbItem[];
        class?: string;
    } = $props();
</script>

<nav
    aria-label="Breadcrumb"
    class={cn(
        'flex min-w-0 items-center gap-1.5 text-sm text-muted-foreground',
        className,
    )}
>
    <Link
        href={home.url()}
        class="inline-flex shrink-0 items-center gap-1.5 hover:text-brand-accent"
    >
        <House class="size-4 shrink-0" />
        <!-- Auf dem Handy nur das Icon, damit die aktuelle Seite nicht abgeschnitten wird -->
        <span class="sr-only sm:not-sr-only">Startseite</span>
    </Link>
    {#each items as item, index (index)}
        <ChevronRight class="size-3.5 shrink-0" />
        {#if index === items.length - 1 || !item.href}
            <span class="truncate text-foreground" aria-current="page">
                {item.title}
            </span>
        {:else}
            <Link href={item.href} class="shrink-0 hover:text-brand-accent">
                {item.title}
            </Link>
        {/if}
    {/each}
</nav>
