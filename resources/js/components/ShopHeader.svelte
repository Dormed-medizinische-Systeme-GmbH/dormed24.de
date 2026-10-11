<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import Menu from '@lucide/svelte/icons/menu';
    import Search from '@lucide/svelte/icons/search';
    import ShoppingCart from '@lucide/svelte/icons/shopping-cart';
    import User from '@lucide/svelte/icons/user';
    import X from '@lucide/svelte/icons/x';
    import * as ProductController from '@/actions/App/Http/Controllers/ProductController';
    import * as SearchController from '@/actions/App/Http/Controllers/SearchController';
    import AppLogo from '@/components/AppLogo.svelte';
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    import CartSheet from '@/components/CartSheet.svelte'; // benötigt für auskommentierte Sidebar-Variante unten
    import CookieBanner from '@/components/CookieBanner.svelte';
    import TestModeBanner from '@/components/TestModeBanner.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import {
        Sheet,
        SheetContent,
        SheetTitle,
        SheetTrigger,
    } from '@/components/ui/sheet';
    import UserSettingsDialog from '@/components/UserSettingsDialog.svelte';
    import { formatPrice } from '@/lib/currency';
    import { toUrl } from '@/lib/utils';
    import cartRoutes from '@/routes/cart';
    import { edit as editProfile } from '@/routes/profile';
    import type { Cart } from '@/types';

    type NavCategory = { slug: string; name: string };

    const navCategories = $derived(page.props.navCategories as NavCategory[]);

    // Aktiv ist die Kategorie der Seite, auch für deren Produktseiten
    // (/{kategorie}/{produkt}).
    const activeCategorySlug = $derived(
        page.url.split(/[?#]/)[0].split('/')[1] ?? '',
    );

    let mobileMenuOpen = $state(false);

    const auth = $derived(page.props.auth);
    const cart = $derived(page.props.cart as Cart);

    type SearchResult = {
        id: number;
        name: string;
        slug: string;
        category_slug: string;
        price: string;
        image_url: string | null;
    };

    let query = $state('');
    let results = $state<SearchResult[]>([]);
    let total = $state(0);
    let isOpen = $state(false);
    let isLoading = $state(false);
    let activeResultIndex = $state(-1);
    let debounceTimer: ReturnType<typeof setTimeout>;
    let searchContainerEl: HTMLDivElement;

    $effect(() => {
        const q = query;
        clearTimeout(debounceTimer);

        if (q.length < 2) {
            results = [];
            total = 0;
            isOpen = false;
            activeResultIndex = -1;

            return;
        }

        isLoading = true;
        debounceTimer = setTimeout(async () => {
            const url = SearchController.index.url({ query: { q } });
            const res = await fetch(url, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            results = data.results ?? [];
            total = data.total ?? 0;
            isOpen = true;
            isLoading = false;
            activeResultIndex = results.length > 0 ? 0 : -1;
        }, 300);

        return () => clearTimeout(debounceTimer);
    });

    function productUrl(product: SearchResult): string {
        return ProductController.show.url({
            category: product.category_slug,
            product: product.slug,
        });
    }

    function clearSearch() {
        query = '';
        results = [];
        isOpen = false;
        activeResultIndex = -1;
    }

    function handleKeydown(e: KeyboardEvent) {
        if (e.key === 'Escape') {
            isOpen = false;
            activeResultIndex = -1;
        }
    }

    function handleSearchKeydown(e: KeyboardEvent) {
        if (!isOpen || results.length === 0) {
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeResultIndex = (activeResultIndex + 1) % results.length;
        }

        if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeResultIndex =
                activeResultIndex <= 0
                    ? results.length - 1
                    : activeResultIndex - 1;
        }

        if (e.key === 'Enter' && activeResultIndex >= 0) {
            e.preventDefault();
            isOpen = false;
            router.visit(productUrl(results[activeResultIndex]));
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
        SearchController.index.url({ query: { q: query } }),
    );
    const accountHref = $derived(auth?.user ? toUrl(editProfile()) : '/login');
</script>

<svelte:window onclick={handleClickOutside} onkeydown={handleKeydown} />

<TestModeBanner />
<header class="sticky top-0 z-40 bg-white shadow-sm">
    <!-- Top row: logo + search + icons -->
    <div class="border-b">
        <div
            class="mx-auto flex h-16 max-w-7xl items-center gap-6 px-4 lg:px-8"
        >
            <!-- Logo -->
            <Link href="/" class="shrink-0">
                <AppLogo />
            </Link>

            <!-- Search (hidden on mobile) -->
            <div
                bind:this={searchContainerEl}
                class="relative mx-8 hidden flex-1 md:block"
            >
                <div class="relative">
                    <Input
                        type="search"
                        placeholder="Suchbegriff eingeben ..."
                        bind:value={query}
                        onfocus={() => query.length >= 2 && (isOpen = true)}
                        onkeydown={handleSearchKeydown}
                        class="pr-10 [&::-webkit-search-cancel-button]:hidden"
                    />
                    {#if query}
                        <Button
                            variant="ghost"
                            size="icon"
                            onclick={clearSearch}
                            class="absolute right-0 top-0 h-full px-3 text-muted-foreground hover:text-foreground"
                        >
                            <X class="size-4" />
                        </Button>
                    {:else}
                        <span
                            class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground"
                        >
                            <Search class="size-4" />
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
                                {#each results as product, index (product.id)}
                                    <li class="border-b last:border-b-0">
                                        <Link
                                            href={productUrl(product)}
                                            class={`flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-accent ${activeResultIndex === index ? 'bg-[#eef5ff] text-primary' : ''}`}
                                            onclick={() => (isOpen = false)}
                                            onmouseenter={() =>
                                                (activeResultIndex = index)}
                                        >
                                            <div
                                                class="size-9 shrink-0 overflow-hidden rounded bg-muted"
                                            >
                                                {#if product.image_url}
                                                    <img
                                                        src={product.image_url}
                                                        alt={product.name}
                                                        class="size-full object-cover object-center"
                                                    />
                                                {/if}
                                            </div>
                                            <span
                                                class="flex-1 truncate text-sm"
                                            >
                                                {product.name}
                                            </span>
                                            <span
                                                class="shrink-0 text-sm font-medium text-brand-secondary"
                                            >
                                                {formatPrice(product.price)}*
                                            </span>
                                        </Link>
                                    </li>
                                {/each}
                            </ul>
                            <Link
                                href={allResultsUrl}
                                class="flex items-center justify-between border-t px-4 py-2.5 text-sm text-brand-accent hover:bg-accent"
                                onclick={() => (isOpen = false)}
                            >
                                <span
                                    class="flex items-center gap-1 font-medium"
                                >
                                    <ChevronRight class="size-4" />
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
                <!-- Mobile: burger menu -->
                <Sheet bind:open={mobileMenuOpen}>
                    <SheetTrigger asChild>
                        {#snippet children(props)}
                            <Button
                                variant="ghost"
                                size="icon"
                                class="md:hidden"
                                onclick={props.onclick}
                            >
                                <Menu class="size-5" />
                                <span class="sr-only">Menü</span>
                            </Button>
                        {/snippet}
                    </SheetTrigger>
                    <SheetContent side="left" class="w-72 pb-0">
                        <SheetTitle class="sr-only">Navigation</SheetTitle>
                        <Link href="/" class="inline-flex">
                            <AppLogo />
                        </Link>
                        <nav class="mt-6 flex flex-col gap-1">
                            {#each navCategories as cat (cat.slug)}
                                <Link
                                    href={`/${cat.slug}`}
                                    class={`rounded-md px-3 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-brand-secondary ${cat.slug === activeCategorySlug ? 'text-brand-secondary' : 'text-muted-foreground'}`}
                                    aria-current={cat.slug ===
                                    activeCategorySlug
                                        ? 'page'
                                        : undefined}
                                >
                                    {cat.name}
                                </Link>
                            {/each}
                        </nav>

                        <!-- Konto + Warenkorb mit Wert; mobil die einzigen Zugänge (Header-Icons erst ab md) -->
                        <div
                            class="-mx-6 mt-auto flex flex-col gap-2 border-t p-4"
                        >
                            <Link
                                href={accountHref}
                                onclick={() => (mobileMenuOpen = false)}
                                class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg border bg-white px-4 text-sm font-semibold text-brand-secondary transition hover:bg-muted"
                            >
                                <User class="size-4" />
                                {auth?.user ? 'Profil' : 'Anmelden'}
                            </Link>
                            <Link
                                href={cartRoutes.index.url()}
                                onclick={() => (mobileMenuOpen = false)}
                                class="inline-flex h-10 w-full items-center justify-between gap-3 rounded-lg bg-primary px-4 text-sm font-semibold text-white transition hover:bg-primary/90"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <span class="relative">
                                        <ShoppingCart class="size-4" />
                                        {#if cart.count > 0}
                                            <span
                                                class="absolute -right-2 -top-2 flex size-4 items-center justify-center rounded-full bg-brand-accent text-[10px] font-bold text-white"
                                            >
                                                {cart.count}
                                            </span>
                                        {/if}
                                    </span>
                                    Warenkorb
                                </span>
                                <span class="tabular-nums">
                                    {formatPrice(cart.subtotal)}*
                                </span>
                            </Link>
                        </div>
                    </SheetContent>
                </Sheet>

                <!-- Direktlink zum Warenkorb (aktiv); mobil nur im Burger-Menü -->
                <Button
                    variant="ghost"
                    class="hidden gap-2 px-3 md:inline-flex"
                    asChild
                >
                    {#snippet children(props)}
                        <Link href={cartRoutes.index.url()} class={props.class}>
                            <span
                                class="text-sm font-medium text-brand-secondary"
                            >
                                {formatPrice(cart.subtotal)}*
                            </span>
                            <div class="relative">
                                <ShoppingCart class="size-5" />
                                {#if cart.count > 0}
                                    <span
                                        class="absolute -right-2 -top-2 flex size-4 items-center justify-center rounded-full bg-brand-accent text-[10px] font-bold text-white"
                                    >
                                        {cart.count}
                                    </span>
                                {/if}
                            </div>
                        </Link>
                    {/snippet}
                </Button>

                <!--
                    BEWUSST AUSKOMMENTIERT — Cart-Sidebar-Variante
                    Zum Aktivieren: diesen Block einkommentieren und den Direktlink-Button oben löschen.
                    <CartSheet {cart} />
                -->

                <Button
                    variant="ghost"
                    size="icon"
                    class="hidden md:inline-flex"
                    asChild
                >
                    {#snippet children(props)}
                        <Link
                            href={accountHref}
                            class={props.class}
                            prefetch={auth?.user ? true : undefined}
                        >
                            <User class="size-5" />
                            <span class="sr-only"
                                >{auth?.user ? 'Profil' : 'Anmelden'}</span
                            >
                        </Link>
                    {/snippet}
                </Button>

                <!-- UX-EXPERIMENT: Settings-Modal statt /settings-Seiten;
                     eine der beiden Varianten fliegt nach Bewertung raus. -->
                <UserSettingsDialog />
            </div>
        </div>
    </div>

    <!-- Nav row (hidden on mobile) -->
    <div class="hidden border-t md:block">
        <div class="mx-auto max-w-7xl px-4 lg:px-8">
            <nav class="flex items-center justify-center">
                {#each navCategories as cat (cat.slug)}
                    <Link
                        href={`/${cat.slug}`}
                        class={`border-b-2 border-transparent px-4 py-3 text-sm font-medium transition-colors hover:border-brand-accent hover:text-brand-secondary ${cat.slug === activeCategorySlug ? 'text-brand-secondary' : 'text-muted-foreground'}`}
                        aria-current={cat.slug === activeCategorySlug
                            ? 'page'
                            : undefined}
                    >
                        {cat.name}
                    </Link>
                {/each}
            </nav>
        </div>
    </div>
</header>

<CookieBanner />
