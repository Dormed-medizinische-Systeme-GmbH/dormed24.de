<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import ArrowLeft from '@lucide/svelte/icons/arrow-left';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import Menu from '@lucide/svelte/icons/menu';
    import Search from '@lucide/svelte/icons/search';
    import ShoppingCart from '@lucide/svelte/icons/shopping-cart';
    import User from '@lucide/svelte/icons/user';
    import X from '@lucide/svelte/icons/x';
    import { slide } from 'svelte/transition';
    import * as ProductController from '@/actions/App/Http/Controllers/ProductController';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { toUrl } from '@/lib/utils';
    import cartRoutes from '@/routes/cart';
    import { edit as editProfile } from '@/routes/profile';
    import type { Cart } from '@/types';

    type MegaMenuKey = 'artikelgruppen' | 'verbrauchsartikel' | null;
    type NavCategory = { id: number; name: string; slug: string };

    // TEST-DATEN: Diese Navigationsstruktur (zwei Mega-Menu-Gruppen) ist
    // erfunden, es gibt aktuell nur eine flache Kategorie-Liste im Backend
    // (navCategories). Beide Mega-Menus zeigen deshalb dieselben echten
    // Kategorien - siehe TODO.md fuer die Diskussion, wie das langfristig
    // aussehen soll.
    const navItems = [
        { label: 'Home', href: '/', menu: null },
        {
            label: 'Artikelgruppen',
            href: '/artikelgruppen',
            menu: 'artikelgruppen' as MegaMenuKey,
        },
        {
            label: 'Verbrauchsartikel',
            href: '/verbrauchsartikel',
            menu: 'verbrauchsartikel' as MegaMenuKey,
        },
        { label: 'Hilfe & Kontakt', href: '/hilfe', menu: null },
    ] as const;

    const navCategories = $derived(page.props.navCategories as NavCategory[]);

    const megaMenus = $derived({
        artikelgruppen: navCategories.map((cat) => ({
            label: cat.name,
            href: `/${cat.slug}`,
        })),
        verbrauchsartikel: navCategories.map((cat) => ({
            label: cat.name,
            href: `/${cat.slug}`,
        })),
    } as Record<string, { label: string; href: string }[]>);

    let activeMenu = $state<MegaMenuKey>(null);
    let closeTimer: ReturnType<typeof setTimeout>;

    function openMenu(key: MegaMenuKey) {
        clearTimeout(closeTimer);
        activeMenu = key;
    }

    function scheduleClose() {
        closeTimer = setTimeout(() => {
            activeMenu = null;
        }, 120);
    }

    function closeMenu() {
        clearTimeout(closeTimer);
        activeMenu = null;
    }

    // Mobile: eigenes Slide-down-Panel unterhalb des Headers statt Sheet/
    // Modal wie im Haupt-ShopHeader. mobilePanel === null zeigt die
    // Oberpunkte, sonst die Unterpunkte des gewaehlten Mega-Menus mit
    // Zurueck-Zeile.
    let mobileNavOpen = $state(false);
    let mobilePanel = $state<MegaMenuKey>(null);

    function toggleMobileNav() {
        mobileNavOpen = !mobileNavOpen;

        if (!mobileNavOpen) {
            mobilePanel = null;
        }
    }

    function closeMobileNav() {
        mobileNavOpen = false;
        mobilePanel = null;
    }

    $effect(() => {
        if (typeof document === 'undefined') {
            return;
        }

        document.body.style.overflow = mobileNavOpen ? 'hidden' : '';

        return () => {
            document.body.style.overflow = '';
        };
    });

    const auth = $derived(page.props.auth);
    const cart = $derived(page.props.cart as Cart);

    type SearchResult = { id: number; name: string; price: string };

    let query = $state('');
    let results = $state<SearchResult[]>([]);
    let total = $state(0);
    let isOpen = $state(false);
    let isLoading = $state(false);
    let debounceTimer: ReturnType<typeof setTimeout>;
    let searchContainerEl: HTMLDivElement;

    function formatPrice(value: number | string): string {
        return new Intl.NumberFormat('de-DE', {
            style: 'currency',
            currency: 'EUR',
        }).format(Number(value));
    }

    $effect(() => {
        const q = query;
        clearTimeout(debounceTimer);

        if (q.length < 2) {
            results = [];
            total = 0;
            isOpen = false;

            return;
        }

        isLoading = true;
        debounceTimer = setTimeout(async () => {
            const url = ProductController.search.url({ query: { q } });
            const res = await fetch(url, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            results = data.results ?? [];
            total = data.total ?? 0;
            isOpen = true;
            isLoading = false;
        }, 300);

        return () => clearTimeout(debounceTimer);
    });

    function clearSearch() {
        query = '';
        results = [];
        isOpen = false;
    }

    function handleKeydown(e: KeyboardEvent) {
        if (e.key === 'Escape') {
            isOpen = false;

            if (mobileNavOpen) {
                closeMobileNav();
            }
        }
    }

    function handleClickOutside(e: MouseEvent) {
        if (
            searchContainerEl &&
            !searchContainerEl.contains(e.target as Node)
        ) {
            isOpen = false;
        }
    }

    const allResultsUrl = $derived(
        ProductController.index.url({ query: { q: query } }),
    );
</script>

<svelte:window onclick={handleClickOutside} onkeydown={handleKeydown} />

<header class="sticky top-0 z-40 bg-white shadow-sm">
    <!-- Top row: logo + search + icons -->
    <div class="border-b">
        <div
            class="mx-auto flex h-16 max-w-7xl items-center gap-6 px-4 lg:px-8"
        >
            <!-- Logo -->
            <Link href="/" class="shrink-0">
                <img src="/logo.svg" alt="dormed 24" class="h-10 w-auto" />
            </Link>

            <!-- Search (hidden on mobile, wie im Haupt-ShopHeader) -->
            <div
                bind:this={searchContainerEl}
                class="relative hidden flex-1 md:block"
            >
                <div class="relative">
                    <Input
                        type="search"
                        placeholder="Suchbegriff eingeben ..."
                        bind:value={query}
                        onfocus={() => query.length >= 2 && (isOpen = true)}
                        class="pr-10 [&::-webkit-search-cancel-button]:hidden"
                    />
                    {#if query}
                        <Button
                            variant="ghost"
                            size="icon"
                            onclick={clearSearch}
                            class="absolute right-0 top-0 h-full px-3 text-muted-foreground hover:text-foreground"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                    {:else}
                        <span
                            class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground"
                        >
                            <Search class="h-4 w-4" />
                        </span>
                    {/if}
                </div>

                <!-- Search dropdown -->
                {#if isOpen && query.length >= 2}
                    <div
                        class="absolute left-0 right-0 top-[calc(100%+4px)] z-50 overflow-hidden rounded-md border bg-white shadow-lg"
                    >
                        {#if isLoading}
                            <div
                                class="px-4 py-3 text-sm text-muted-foreground"
                            >
                                Suche ...
                            </div>
                        {:else if results.length === 0}
                            <div
                                class="px-4 py-3 text-sm text-muted-foreground"
                            >
                                Keine Ergebnisse für „{query}"
                            </div>
                        {:else}
                            <ul>
                                {#each results as product (product.id)}
                                    <li class="border-b last:border-b-0">
                                        <Link
                                            href="/products/{product.id}"
                                            class="flex items-center gap-3 px-4 py-2.5 hover:bg-accent"
                                            onclick={() => (isOpen = false)}
                                        >
                                            <div
                                                class="h-9 w-9 shrink-0 rounded bg-gray-100"
                                            ></div>
                                            <span
                                                class="flex-1 truncate text-sm"
                                            >
                                                {product.name}
                                            </span>
                                            <span
                                                class="shrink-0 text-sm font-medium text-[#1a3a5c]"
                                            >
                                                {formatPrice(product.price)}*
                                            </span>
                                        </Link>
                                    </li>
                                {/each}
                            </ul>
                            <Link
                                href={allResultsUrl}
                                class="flex items-center justify-between border-t px-4 py-2.5 text-sm text-[#1a6bbf] hover:bg-accent"
                                onclick={() => (isOpen = false)}
                            >
                                <span
                                    class="flex items-center gap-1 font-medium"
                                >
                                    <ChevronRight class="h-4 w-4" />
                                    Alle Suchergebnisse anzeigen
                                </span>
                                <span class="text-muted-foreground">
                                    {total}
                                    {total === 1 ? 'Ergebnis' : 'Ergebnisse'}
                                </span>
                            </Link>
                        {/if}
                    </div>
                {/if}
            </div>

            <!-- Actions -->
            <div class="ml-auto flex shrink-0 items-center gap-1">
                <!--
                    TEST-SIMPLIFIZIERUNG: kein Dropdown mehr - Klick geht
                    direkt zu Login bzw. Profil. Registrieren ist nur noch
                    ueber den Link im Login-Formular oder per URL erreichbar.
                -->
                <Button
                    variant="ghost"
                    size="icon"
                    class="hidden md:inline-flex"
                    asChild
                >
                    {#snippet children(props)}
                        <Link
                            href={auth?.user ? toUrl(editProfile()) : '/login'}
                            class={props.class}
                        >
                            <User class="h-5 w-5" />
                            <span class="sr-only">
                                {auth?.user ? 'Profil' : 'Anmelden'}
                            </span>
                        </Link>
                    {/snippet}
                </Button>

                <Button variant="ghost" class="gap-2 px-3">
                    <div class="relative">
                        <ShoppingCart class="h-5 w-5" />
                        {#if cart.count > 0}
                            <span
                                class="absolute -right-2 -top-2 flex h-4 w-4 items-center justify-center rounded-full bg-[#1a6bbf] text-[10px] font-bold text-white"
                            >
                                {cart.count}
                            </span>
                        {/if}
                    </div>
                    <span class="text-sm font-medium text-[#1a3a5c]">
                        {cart.total}*
                    </span>
                </Button>

                <Button
                    variant="ghost"
                    size="icon"
                    class="md:hidden"
                    onclick={toggleMobileNav}
                    aria-expanded={mobileNavOpen}
                    aria-label={mobileNavOpen
                        ? 'Menü schließen'
                        : 'Menü öffnen'}
                >
                    {#if mobileNavOpen}
                        <X class="h-5 w-5" />
                    {:else}
                        <Menu class="h-5 w-5" />
                    {/if}
                </Button>
            </div>
        </div>
    </div>

    <!-- Nav row + mega menu (Desktop) -->
    <div class="relative hidden md:block">
        <div class="mx-auto max-w-7xl px-4 lg:px-8">
            <nav class="flex items-center justify-center">
                {#each navItems as item (item.label)}
                    {#if item.menu}
                        <button
                            class="flex items-center gap-0.5 border-b-2 px-4 py-3 text-sm font-medium transition-colors
                                {activeMenu === item.menu
                                ? 'border-[#1a6bbf] text-[#1a3a5c]'
                                : 'border-transparent text-gray-600 hover:text-[#1a3a5c]'}"
                            onmouseenter={() => openMenu(item.menu)}
                            onmouseleave={scheduleClose}
                        >
                            {item.label}
                            <ChevronRight
                                class="h-3.5 w-3.5 transition-transform duration-150 {activeMenu ===
                                item.menu
                                    ? 'rotate-90'
                                    : ''}"
                            />
                        </button>
                    {:else}
                        <Link
                            href={item.href}
                            class="border-b-2 border-transparent px-4 py-3 text-sm font-medium text-gray-600 transition-colors hover:border-[#1a6bbf] hover:text-[#1a3a5c]"
                        >
                            {item.label}
                        </Link>
                    {/if}
                {/each}
            </nav>
        </div>

        <!-- Mega menu panel -->
        {#if activeMenu && megaMenus[activeMenu]}
            <div
                class="absolute left-0 right-0 top-full z-40 border-t border-b bg-white shadow-lg"
                onmouseenter={() => openMenu(activeMenu)}
                onmouseleave={scheduleClose}
            >
                <div class="mx-auto max-w-7xl px-4 py-4 lg:px-8">
                    <div class="flex flex-wrap justify-center">
                        {#each megaMenus[activeMenu] as cat (cat.label)}
                            <Link
                                href={cat.href}
                                onclick={closeMenu}
                                class="border-l border-gray-300 px-5 py-1 text-sm font-medium text-[#1a3a5c] transition-colors hover:text-[#1a6bbf]"
                            >
                                {cat.label}
                            </Link>
                        {/each}
                    </div>
                </div>
            </div>
        {/if}
    </div>

    <!--
        Mobile: eigenes Slide-down-Panel statt Sheet/Modal wie im Haupt-
        ShopHeader. position:fixed (viewport-relativ) mit top-16, da die
        Header-Hoehe auf Mobile immer h-16 ist (Such-/Nav-Zeile sind dort
        per hidden ausgeblendet) - kein absolute+top-full mehr, das hatte
        seine Containing-Block-Hoehe vom <header> geerbt und liess sich
        deshalb nicht sauber bis zur Viewport-Unterkante ausdehnen (bottom-0
        waere relativ zum Header gewesen, nicht zum Viewport). Nur
        gemountet wenn offen ({#if}, nicht dauerhaft im DOM mit Transform-
        Toggle) - ein permanent vorhandenes, transformiertes Element hat im
        Browser dazu gefuehrt, dass der Header beim ersten Paint leer blieb,
        bis eine Interaktion einen Repaint erzwang. transition:slide
        uebernimmt die Auf-/Zuklapp-Animation.
    -->
    {#if mobileNavOpen}
        <div
            class="fixed inset-x-0 top-16 bottom-0 z-30 flex flex-col bg-white md:hidden"
            transition:slide={{ duration: 420 }}
        >
            <div class="flex-1 overflow-y-auto">
                {#if mobilePanel === null}
                    <nav class="flex flex-col divide-y">
                        {#each navItems as item (item.label)}
                            {#if item.menu}
                                <button
                                    type="button"
                                    class="flex items-center justify-between px-4 py-4 text-left text-base font-medium text-foreground"
                                    onclick={() => (mobilePanel = item.menu)}
                                >
                                    {item.label}
                                    <ChevronRight
                                        class="size-4 text-muted-foreground"
                                    />
                                </button>
                            {:else}
                                <Link
                                    href={item.href}
                                    class="px-4 py-4 text-base font-medium text-foreground"
                                    onclick={closeMobileNav}
                                >
                                    {item.label}
                                </Link>
                            {/if}
                        {/each}
                    </nav>
                {:else}
                    <div class="flex flex-col">
                        <button
                            type="button"
                            class="flex items-center gap-2 border-b px-4 py-4 text-left"
                            onclick={() => (mobilePanel = null)}
                        >
                            <ArrowLeft
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                            <span
                                class="text-base font-semibold text-foreground"
                            >
                                {navItems.find((n) => n.menu === mobilePanel)
                                    ?.label}
                            </span>
                        </button>
                        <nav class="flex flex-col divide-y">
                            {#each megaMenus[mobilePanel ?? ''] ?? [] as cat (cat.label)}
                                <Link
                                    href={cat.href}
                                    class="px-4 py-4 text-base text-foreground"
                                    onclick={closeMobileNav}
                                >
                                    {cat.label}
                                </Link>
                            {/each}
                        </nav>
                    </div>
                {/if}
            </div>

            <!-- Warenkorb + Login/Profil, immer unten (mt-auto) -->
            <div class="mt-auto flex flex-col gap-2 border-t p-4">
                <Button
                    variant="secondary"
                    class="w-full justify-center"
                    asChild
                >
                    {#snippet children(props)}
                        <Link
                            href={auth?.user ? toUrl(editProfile()) : '/login'}
                            class={props.class}
                            onclick={closeMobileNav}
                        >
                            <User class="mr-2 size-4" />
                            {auth?.user ? 'Profil anzeigen' : 'Einloggen'}
                        </Link>
                    {/snippet}
                </Button>
                <Button class="w-full justify-center" asChild>
                    {#snippet children(props)}
                        <Link
                            href={cartRoutes.index.url()}
                            class={props.class}
                            onclick={closeMobileNav}
                        >
                            <span class="mr-1 font-semibold">
                                {cart.total}
                            </span>
                            <ShoppingCart class="mr-2 size-4" />
                            Warenkorb
                        </Link>
                    {/snippet}
                </Button>
            </div>
        </div>
    {/if}
</header>
