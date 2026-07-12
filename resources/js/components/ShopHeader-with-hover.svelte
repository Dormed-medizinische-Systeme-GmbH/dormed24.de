<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import LogOut from '@lucide/svelte/icons/log-out';
    import Search from '@lucide/svelte/icons/search';
    import Settings from '@lucide/svelte/icons/settings';
    import ShoppingCart from '@lucide/svelte/icons/shopping-cart';
    import User from '@lucide/svelte/icons/user';
    import UserPlus from '@lucide/svelte/icons/user-plus';
    import X from '@lucide/svelte/icons/x';
    import * as ProductController from '@/actions/App/Http/Controllers/ProductController';
    import CustomerInfo from '@/components/CustomerInfo.svelte';
    import { Button } from '@/components/ui/button';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuGroup,
        DropdownMenuItem,
        DropdownMenuLabel,
        DropdownMenuSeparator,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import { Input } from '@/components/ui/input';
    import { toUrl } from '@/lib/utils';
    import { logout } from '@/routes';
    import { edit as editProfile } from '@/routes/profile';
    import type { Cart, Customer } from '@/types';

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

<header class="relative z-40 bg-white shadow-sm">
    <!-- Top row: logo + search + icons -->
    <div class="border-b">
        <div
            class="mx-auto flex h-16 max-w-7xl items-center gap-6 px-4 lg:px-8"
        >
            <!-- Logo -->
            <Link href="/" class="shrink-0">
                <img src="/logo.svg" alt="dormed 24" class="h-10 w-auto" />
            </Link>

            <!-- Search -->
            <div bind:this={searchContainerEl} class="relative flex-1">
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
            <div class="flex shrink-0 items-center gap-1">
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        {#snippet children(props)}
                            <Button variant="ghost" size="icon" {...props}>
                                <User class="h-5 w-5" />
                                <span class="sr-only">Konto</span>
                            </Button>
                        {/snippet}
                    </DropdownMenuTrigger>
                    <DropdownMenuContent class="w-56" align="end">
                        {#if auth?.user}
                            <DropdownMenuLabel class="p-0 font-normal">
                                <div
                                    class="flex items-center gap-2 px-1 py-1.5 text-left text-sm"
                                >
                                    <CustomerInfo
                                        user={auth.user as Customer}
                                        showEmail={true}
                                    />
                                </div>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuGroup>
                                <DropdownMenuItem asChild>
                                    {#snippet children(props)}
                                        <Link
                                            class={props.class}
                                            href={toUrl(editProfile())}
                                            prefetch
                                            onclick={props.onClick}
                                        >
                                            <Settings class="mr-2 h-4 w-4" />
                                            Einstellungen
                                        </Link>
                                    {/snippet}
                                </DropdownMenuItem>
                            </DropdownMenuGroup>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild>
                                {#snippet children(props)}
                                    <Link
                                        class={props.class}
                                        href={logout()}
                                        as="button"
                                        onclick={() => {
                                            props.onClick?.();
                                            router.flushAll();
                                        }}
                                    >
                                        <LogOut class="mr-2 h-4 w-4" />
                                        Abmelden
                                    </Link>
                                {/snippet}
                            </DropdownMenuItem>
                        {:else}
                            <DropdownMenuGroup>
                                <DropdownMenuItem asChild>
                                    {#snippet children(props)}
                                        <Link
                                            class={props.class}
                                            href="/login"
                                            onclick={props.onClick}
                                        >
                                            <User class="mr-2 h-4 w-4" />
                                            Anmelden
                                        </Link>
                                    {/snippet}
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    {#snippet children(props)}
                                        <Link
                                            class={props.class}
                                            href="/register"
                                            onclick={props.onClick}
                                        >
                                            <UserPlus class="mr-2 h-4 w-4" />
                                            Registrieren
                                        </Link>
                                    {/snippet}
                                </DropdownMenuItem>
                            </DropdownMenuGroup>
                        {/if}
                    </DropdownMenuContent>
                </DropdownMenu>

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
            </div>
        </div>
    </div>

    <!-- Nav row + mega menu -->
    <div class="relative">
        <div class="mx-auto max-w-7xl px-4 lg:px-8">
            <nav class="flex items-center">
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
                    <div class="mb-3 flex items-center justify-between">
                        <Link
                            href={navItems.find((n) => n.menu === activeMenu)
                                ?.href ?? '/'}
                            class="flex items-center gap-1 text-sm font-semibold text-[#1a3a5c] hover:underline"
                            onclick={closeMenu}
                        >
                            Zur Kategorie {navItems.find(
                                (n) => n.menu === activeMenu,
                            )?.label}
                            <ChevronRight class="h-4 w-4" />
                        </Link>
                        <button
                            onclick={closeMenu}
                            class="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700"
                            aria-label="Menü schließen"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="flex flex-wrap">
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
</header>
