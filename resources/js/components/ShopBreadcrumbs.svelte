<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import House from '@lucide/svelte/icons/house';
    import { cn } from '@/lib/utils';
    import { home } from '@/routes';

    /**
     * Breadcrumb der öffentlichen Shop-Seiten (Produktlisten + Produktansicht).
     * "Startseite" steht immer vorne; das letzte Element ist die aktuelle
     * Seite und wird ohne Link dargestellt.
     *
     * Gibt aus denselben Einträgen das schema.org-BreadcrumbList-JSON-LD aus,
     * damit sichtbare und strukturierte Breadcrumbs nicht auseinanderlaufen.
     * `inSchema: false` blendet einen Eintrag nur im JSON-LD aus (z. B. die
     * Suche, die für Suchmaschinen keine eigene Ebene ist).
     */
    type ShopBreadcrumbItem = {
        title: string;
        href?: string;
        inSchema?: boolean;
    };

    let {
        items,
        class: className = '',
    }: {
        items: ShopBreadcrumbItem[];
        class?: string;
    } = $props();

    function absoluteUrl(path: string): string {
        const base =
            page.props.appUrl ||
            (typeof window !== 'undefined' ? window.location.origin : null);

        return base ? new URL(path, base).href : path;
    }

    const breadcrumbSchemaHtml = $derived.by(() => {
        const entries = [
            { title: 'Startseite', href: home.url() },
            ...items.filter((item) => item.inSchema !== false),
        ];

        const schema = {
            '@context': 'https://schema.org',
            '@type': 'BreadcrumbList',
            itemListElement: entries.map((entry, index) => ({
                '@type': 'ListItem',
                position: index + 1,
                name: entry.title,
                // Laut Google darf nur das letzte Element (aktuelle Seite)
                // ohne URL auskommen.
                ...(entry.href ? { item: absoluteUrl(entry.href) } : {}),
            })),
        };

        // "<" escapen, damit Admin-Inhalte (Produkt-/Kategorienamen) das
        // umgebende script-Tag nicht vorzeitig schließen können.
        const json = JSON.stringify(schema).replace(/</g, '\\u003c');

        return `<script type="application/ld+json">${json}<` + `/script>`;
    });
</script>

<svelte:head>
    <!-- eslint-disable-next-line svelte/no-at-html-tags -- JSON-LD; "<" ist escaped -->
    {@html breadcrumbSchemaHtml}
</svelte:head>

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
