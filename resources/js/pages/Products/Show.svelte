<script lang="ts">
    import { Form, Link, router } from '@inertiajs/svelte';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import Minus from '@lucide/svelte/icons/minus';
    import Plus from '@lucide/svelte/icons/plus';
    import ShoppingCart from '@lucide/svelte/icons/shopping-cart';
    import Star from '@lucide/svelte/icons/star';
    import AppFooter from '@/components/AppFooter.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import ShopHeader from '@/components/ShopHeader.svelte';
    import SortimentShowcase from '@/components/SortimentShowcase.svelte';
    import { Button } from '@/components/ui/button';
    import * as Carousel from '@/components/ui/carousel';
    import * as Empty from '@/components/ui/empty';
    import { Label } from '@/components/ui/label';
    import * as Select from '@/components/ui/select';
    import { Textarea } from '@/components/ui/textarea';
    import { formatPrice } from '@/lib/currency';
    import { cn } from '@/lib/utils';
    import * as CategoryController from '@/actions/App/Http/Controllers/CategoryController';
    import * as ProductController from '@/actions/App/Http/Controllers/ProductController';
    import cartRoutes from '@/routes/cart';

    type ProductImage = { id: number; url: string; sort_order: number };
    type ProductVariant = {
        id: number;
        label: string;
        price: string;
        is_default: boolean;
    };

    type Product = {
        id: number;
        name: string;
        description: string | null;
        price: string;
        is_available: boolean;
        category: { id: number; name: string; slug: string } | null;
        manufacturer: { id: number; name: string } | null;
        images: ProductImage[];
        variants: ProductVariant[];
    };

    type Rating = {
        id: number;
        stars: number;
        content: string;
        created_at: string | null;
    };

    type RatingSummary = {
        average: string | null;
        count: number;
    };

    type SimilarProduct = {
        id: number;
        name: string;
        price: string;
        manufacturer: { id: number; name: string } | null;
        images: ProductImage[];
    };

    let {
        product,
        ratings,
        ratingSummary,
        similarProducts = [],
    }: {
        product: Product;
        ratings: Rating[];
        ratingSummary: RatingSummary;
        similarProducts?: SimilarProduct[];
    } = $props();

    // svelte-ignore state_referenced_locally
    const hasVariants = product.variants.length > 0;
    // svelte-ignore state_referenced_locally
    const defaultVariant =
        product.variants.find((v) => v.is_default) ??
        product.variants[0] ??
        null;

    let selectedVariantValue = $state(
        defaultVariant ? String(defaultVariant.id) : '',
    );
    let quantity = $state(1);
    let activeTab = $state<'beschreibung' | 'bewertungen'>('beschreibung');
    let ratingStars = $state(5);
    let showAllRatings = $state(false);
    const RATINGS_PREVIEW_LIMIT = 3;
    let activeImageIndex = $state(0);
    let carouselApi = $state<any>();

    const selectedVariant = $derived(
        hasVariants
            ? (product.variants.find(
                  (v) => String(v.id) === selectedVariantValue,
              ) ?? null)
            : null,
    );
    const displayedPrice = $derived(
        selectedVariant ? selectedVariant.price : product.price,
    );
    const ratingAverageValue = $derived(
        ratingSummary.average !== null
            ? Number(ratingSummary.average.replace(',', '.'))
            : null,
    );
    const visibleRatings = $derived(
        showAllRatings ? ratings : ratings.slice(0, RATINGS_PREVIEW_LIMIT),
    );
    const variantSelectLabel = $derived(
        selectedVariant?.label ?? 'Variation waehlen',
    );

    const metaDescription = $derived(
        product.description
            ? product.description.replace(/\n+/g, ' ').trim().slice(0, 160)
            : null,
    );

    const schemaAvailability = $derived(
        product.is_available
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock',
    );

    const productUrl = $derived.by(() => {
        const path = ProductController.show.url(product.id);

        return typeof window === 'undefined'
            ? path
            : new URL(path, window.location.origin).href;
    });

    const productSchema = $derived.by(() => {
        const schema: Record<string, unknown> = {
            '@context': 'https://schema.org/',
            '@type': 'Product',
            name: product.name,
            sku: String(product.id),
            url: productUrl,
            ...(product.description
                ? { description: product.description }
                : {}),
            ...(product.images.length > 0
                ? { image: product.images.map((img) => img.url) }
                : {}),
            ...(product.manufacturer
                ? {
                      brand: {
                          '@type': 'Brand',
                          name: product.manufacturer.name,
                      },
                  }
                : {}),
            ...(ratingAverageValue !== null && ratingSummary.count > 0
                ? {
                      aggregateRating: {
                          '@type': 'AggregateRating',
                          ratingValue: ratingAverageValue,
                          reviewCount: ratingSummary.count,
                          bestRating: 5,
                          worstRating: 1,
                      },
                  }
                : {}),
        };

        if (hasVariants && product.variants.length > 0) {
            const prices = product.variants.map((v) => parseFloat(v.price));
            schema.offers = {
                '@type': 'AggregateOffer',
                priceCurrency: 'EUR',
                lowPrice: Math.min(...prices).toFixed(2),
                highPrice: Math.max(...prices).toFixed(2),
                offerCount: product.variants.length,
                offers: product.variants.map((v) => ({
                    '@type': 'Offer',
                    name: v.label,
                    price: parseFloat(v.price).toFixed(2),
                    priceCurrency: 'EUR',
                    availability: schemaAvailability,
                    itemCondition: 'https://schema.org/NewCondition',
                })),
            };
        } else {
            schema.offers = {
                '@type': 'Offer',
                price: parseFloat(product.price).toFixed(2),
                priceCurrency: 'EUR',
                availability: schemaAvailability,
                itemCondition: 'https://schema.org/NewCondition',
            };
        }

        // "<" escaped so admin-supplied content (name/description) can never
        // close the surrounding script tag early (script-breakout injection).
        return JSON.stringify(schema).replace(/</g, '\\u003c');
    });

    const jsonLdHtml = $derived(
        `<script type="application/ld+json">${productSchema}<` + `/script>`,
    );

    function starLabel(stars: number): string {
        return `${stars} Stern${stars === 1 ? '' : 'e'}`;
    }

    function addToCart() {
        router.post(
            cartRoutes.items.store.url(),
            {
                product_id: product.id,
                variant_id: selectedVariant?.id ?? null,
                quantity,
            },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    }

    $effect(() => {
        if (!carouselApi || product.images.length <= 1) {
            return;
        }

        const syncFromCarousel = () => {
            activeImageIndex = carouselApi.selectedScrollSnap();
        };

        carouselApi.on('select', syncFromCarousel);
        syncFromCarousel();

        return () => {
            carouselApi.off('select', syncFromCarousel);
        };
    });

    $effect(() => {
        if (!carouselApi) {
            return;
        }

        if (carouselApi.selectedScrollSnap() === activeImageIndex) {
            return;
        }

        carouselApi.scrollTo(activeImageIndex);
    });
</script>

<AppHead
    canonical={ProductController.show.url(product.id)}
    title={product.name}
    description={metaDescription}
    ogImage={product.images[0]?.url ?? null}
    ogType="product"
/>

<svelte:head>
    <!-- eslint-disable-next-line svelte/no-at-html-tags -- JSON-LD; "<" is <-escaped in productSchema -->
    {@html jsonLdHtml}
</svelte:head>

<div class="flex min-h-screen flex-col bg-white">
    <ShopHeader />

    <main class="mx-auto flex-1 max-w-7xl px-4 py-6 lg:px-8">
        <nav
            class="mb-8 flex items-center gap-1.5 text-sm text-muted-foreground"
        >
            <Link
                href={ProductController.index.url()}
                class="hover:text-brand-accent"
            >
                Alle Produkte
            </Link>
            {#if product.category}
                <ChevronRight class="size-3.5 shrink-0" />
                <Link
                    href={CategoryController.show.url(product.category.slug)}
                    class="text-muted-foreground hover:text-brand-accent"
                >
                    {product.category.name}
                </Link>
            {/if}
            <ChevronRight class="size-3.5 shrink-0" />
            <span class="truncate text-foreground">{product.name}</span>
        </nav>

        <div class="grid gap-12 lg:grid-cols-2 lg:items-start">
            <div class="w-full">
                {#if product.images.length > 0}
                    <div class="flex flex-col gap-4">
                        <Carousel.Root
                            class="w-full"
                            setApi={(api) => {
                                carouselApi = api;
                            }}
                        >
                            <Carousel.Content class="-ms-0">
                                {#each product.images as image (image.id)}
                                    <Carousel.Item class="ps-0">
                                        <div
                                            class="aspect-square w-full overflow-hidden rounded-[1.75rem] border border-border bg-white"
                                        >
                                            <img
                                                src={image.url}
                                                alt={product.name}
                                                class="size-full object-contain"
                                            />
                                        </div>
                                    </Carousel.Item>
                                {/each}
                            </Carousel.Content>
                        </Carousel.Root>

                        {#if product.images.length > 1}
                            <div class="grid grid-cols-5 gap-3">
                                {#each product.images as image, i (image.id)}
                                    <button
                                        type="button"
                                        onclick={() => (activeImageIndex = i)}
                                        class={cn(
                                            'aspect-square overflow-hidden rounded-2xl border bg-white transition-colors',
                                            activeImageIndex === i
                                                ? 'border-primary'
                                                : 'border-border hover:border-border',
                                        )}
                                        aria-label={`Bild ${i + 1}`}
                                    >
                                        <img
                                            src={image.url}
                                            alt=""
                                            class="size-full object-contain"
                                        />
                                    </button>
                                {/each}
                            </div>
                        {/if}
                    </div>
                {:else}
                    <div
                        class="flex aspect-square items-center justify-center rounded-[1.75rem] border border-border bg-white"
                    >
                        <ShoppingCart
                            class="size-20 text-border"
                            strokeWidth={1}
                        />
                    </div>
                {/if}
            </div>

            <div class="flex flex-col gap-8">
                <div class="flex flex-col gap-5">
                    <div class="flex flex-col gap-3">
                        {#if product.manufacturer}
                            <span
                                class="text-sm font-medium uppercase tracking-[0.18em] text-brand-accent"
                            >
                                {product.manufacturer.name}
                            </span>
                        {/if}
                        <h1
                            class="max-w-xl text-3xl font-semibold tracking-tight text-[#111827] lg:text-5xl"
                        >
                            {product.name}
                        </h1>
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-3 text-sm text-muted-foreground"
                    >
                        <div class="flex items-center gap-1 text-[#111827]">
                            {#each Array.from({ length: 5 }, (_, index) => index + 1) as star (star)}
                                <Star
                                    class={cn(
                                        'size-4',
                                        ratingAverageValue !== null &&
                                            star <=
                                                Math.round(ratingAverageValue)
                                            ? 'fill-current'
                                            : 'text-muted-foreground',
                                    )}
                                />
                            {/each}
                        </div>
                        <span>
                            {ratingSummary.count > 0
                                ? `${ratingSummary.count} Bewertung${ratingSummary.count === 1 ? '' : 'en'}`
                                : 'Noch keine Bewertungen'}
                        </span>
                    </div>

                    {#if product.description}
                        <p
                            class="max-w-2xl whitespace-pre-line text-base leading-8 text-muted-foreground"
                        >
                            {product.description}
                        </p>
                    {/if}

                    <div class="flex flex-wrap items-end gap-4">
                        <span
                            class="text-4xl font-semibold tracking-tight text-[#111827]"
                        >
                            {formatPrice(displayedPrice)}*
                        </span>
                    </div>

                    <p class="text-sm text-brand-accent hover:underline">
                        <Link href="/informationen/versand"
                            >Preise inkl. MwSt. zzgl. Versandkosten</Link
                        >
                    </p>
                </div>

                <div class="flex flex-col gap-6">
                    {#if hasVariants}
                        <div class="flex flex-col gap-2.5">
                            <Label class="text-sm font-medium text-[#111827]"
                                >Variation</Label
                            >
                            <Select.Root
                                type="single"
                                bind:value={selectedVariantValue}
                            >
                                <Select.Trigger
                                    class="h-12 w-full rounded-xl border-input text-left text-[15px]"
                                >
                                    {variantSelectLabel}
                                </Select.Trigger>
                                <Select.Content>
                                    {#each product.variants as variant (variant.id)}
                                        <Select.Item value={String(variant.id)}>
                                            {variant.label}
                                        </Select.Item>
                                    {/each}
                                </Select.Content>
                            </Select.Root>
                        </div>
                    {/if}

                    <div class="flex items-center gap-2">
                        <span
                            class="size-2.5 shrink-0 rounded-full {product.is_available
                                ? 'bg-emerald-500'
                                : 'bg-red-400'}"
                        ></span>
                        <span class="text-sm text-muted-foreground">
                            {product.is_available
                                ? 'Sofort verfügbar, Lieferzeit: 1-2 Wochen'
                                : 'Derzeit nicht verfügbar'}
                        </span>
                    </div>

                    <div class="flex flex-row items-center gap-3">
                        <div
                            class="flex h-12 items-center rounded-xl border border-input bg-white"
                        >
                            <button
                                class="flex h-full w-12 items-center justify-center text-muted-foreground hover:bg-muted disabled:opacity-40"
                                onclick={() =>
                                    (quantity = Math.max(1, quantity - 1))}
                                disabled={quantity <= 1}
                                aria-label="Menge verringern"
                            >
                                <Minus class="size-4" />
                            </button>
                            <span class="w-14 text-center text-sm font-semibold"
                                >{quantity}</span
                            >
                            <button
                                class="flex h-full w-12 items-center justify-center text-muted-foreground hover:bg-muted"
                                onclick={() => quantity++}
                                aria-label="Menge erhöhen"
                            >
                                <Plus class="size-4" />
                            </button>
                        </div>

                        <Button
                            class="h-12 flex-1 whitespace-nowrap rounded-xl bg-[#111111] text-white hover:bg-[#111111]/90 disabled:opacity-50"
                            onclick={addToCart}
                            disabled={!product.is_available}
                        >
                            <ShoppingCart class="size-4" />
                            {product.is_available
                                ? 'In den Warenkorb'
                                : 'Nicht verfügbar'}
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-12">
            <div class="flex border-b">
                <button
                    class={cn(
                        'px-6 py-3 text-sm font-semibold transition-colors',
                        activeTab === 'beschreibung'
                            ? 'border-b-2 border-brand-accent text-brand-accent'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                    onclick={() => (activeTab = 'beschreibung')}
                >
                    Beschreibung
                </button>
                <button
                    class={cn(
                        'px-6 py-3 text-sm font-semibold transition-colors',
                        activeTab === 'bewertungen'
                            ? 'border-b-2 border-brand-accent text-brand-accent'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                    onclick={() => (activeTab = 'bewertungen')}
                >
                    Bewertungen {ratingSummary.count > 0
                        ? `(${ratingSummary.count})`
                        : ''}
                </button>
            </div>

            <div class="py-8">
                {#if activeTab === 'beschreibung'}
                    <div class="max-w-3xl">
                        <h2 class="mb-4 text-lg font-bold text-foreground">
                            Produktinformationen "{product.name}"
                        </h2>
                        {#if product.description}
                            <p
                                class="whitespace-pre-line text-sm leading-relaxed text-muted-foreground"
                            >
                                {product.description}
                            </p>
                        {:else}
                            <p class="text-sm text-muted-foreground">
                                Keine Beschreibung vorhanden.
                            </p>
                        {/if}
                    </div>
                {:else}
                    <div>
                        <div
                            class="rounded-xl border border-border bg-white p-6"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-4"
                            >
                                <div>
                                    <h2
                                        class="text-lg font-bold text-foreground sm:text-xl"
                                    >
                                        Bewertungen
                                    </h2>
                                    {#if ratingSummary.count > 0}
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            {ratingSummary.count} Bewertung{ratingSummary.count ===
                                            1
                                                ? ''
                                                : 'en'}
                                        </p>
                                    {/if}
                                </div>
                                {#if ratingSummary.count > 0}
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-lg font-bold text-foreground sm:text-xl"
                                        >
                                            {ratingSummary.average}
                                        </span>
                                        <div
                                            class="flex items-center gap-1 text-[#f59e0b]"
                                        >
                                            {#each Array.from({ length: 5 }, (_, index) => index + 1) as star (star)}
                                                <Star
                                                    class={cn(
                                                        'size-5 sm:size-6',
                                                        ratingAverageValue !==
                                                            null &&
                                                            star <=
                                                                Math.round(
                                                                    ratingAverageValue,
                                                                )
                                                            ? 'fill-current'
                                                            : 'text-muted-foreground',
                                                    )}
                                                />
                                            {/each}
                                        </div>
                                    </div>
                                {/if}
                            </div>

                            {#if ratings.length > 0}
                                <div class="mt-6 divide-y divide-gray-100">
                                    {#each visibleRatings as rating (rating.id)}
                                        <article class="py-4 first:pt-0">
                                            <div
                                                class="flex items-center justify-between gap-4"
                                            >
                                                {#if rating.created_at}
                                                    <span
                                                        class="text-sm text-muted-foreground"
                                                        >{rating.created_at}</span
                                                    >
                                                {/if}
                                                <div
                                                    class="flex items-center gap-1 text-[#f59e0b]"
                                                >
                                                    {#each Array.from({ length: 5 }, (_, index) => index + 1) as star (star)}
                                                        <Star
                                                            class={cn(
                                                                'size-4',
                                                                star <=
                                                                    rating.stars
                                                                    ? 'fill-current'
                                                                    : 'text-muted-foreground',
                                                            )}
                                                        />
                                                    {/each}
                                                </div>
                                            </div>
                                            <p
                                                class="mt-2 text-sm leading-6 text-muted-foreground"
                                            >
                                                {rating.content}
                                            </p>
                                        </article>
                                    {/each}
                                </div>

                                {#if ratings.length > RATINGS_PREVIEW_LIMIT}
                                    <div
                                        class="mt-4 border-t border-border pt-4"
                                    >
                                        <Button
                                            variant="outline"
                                            class="w-full"
                                            onclick={() =>
                                                (showAllRatings =
                                                    !showAllRatings)}
                                        >
                                            {showAllRatings
                                                ? 'Weniger anzeigen'
                                                : `Alle ${ratings.length} Bewertungen anzeigen`}
                                        </Button>
                                    </div>
                                {/if}
                            {:else}
                                <Empty.Root class="mt-6 border-none p-0">
                                    <Empty.Header>
                                        <Empty.Media variant="icon">
                                            <Star class="size-5" />
                                        </Empty.Media>
                                        <Empty.Title
                                            >Noch keine Bewertungen</Empty.Title
                                        >
                                        <Empty.Description>
                                            Sei die oder der Erste und teile
                                            deine Erfahrung mit diesem Produkt.
                                        </Empty.Description>
                                    </Empty.Header>
                                </Empty.Root>
                            {/if}
                        </div>

                        <!--
                            TODO: Bewertung darf nicht mehr ueber die Produktseite
                            abgegeben werden - geplant ist ein Modal aus der
                            Bestellung heraus (nur fuer tatsaechlich gekaufte
                            Produkte, z.B. per "hat gekauft"-Middleware).
                            Wenn Rating ordentlich aus der Bestellung heraus
                            implementiert ist, kann das hier wieder reaktiviert
                            und richtig konfiguriert werden.
                            Die zugehoerige Route ist ebenfalls deaktiviert
                            (routes/public/rating.php) - beides wird GEMEINSAM
                            reaktiviert.
                        -->
                        {#if false}
                            <div
                                class="rounded-xl border border-border bg-white p-6"
                            >
                                <h2 class="text-lg font-bold text-foreground">
                                    Bewertung abgeben
                                </h2>
                                <p class="mt-2 text-sm text-muted-foreground">
                                    Ohne Login. Später kann die Moderation oder
                                    Verknüpfung zu Kunden ergänzt werden.
                                </p>

                                <Form
                                    action={`/products/${product.id}/ratings`}
                                    method="post"
                                    resetOnSuccess={['content']}
                                    class="mt-6 space-y-5"
                                >
                                    {#snippet children({ errors, processing })}
                                        <input
                                            type="hidden"
                                            name="stars"
                                            value={ratingStars}
                                        />

                                        <div class="grid gap-2">
                                            <Label for="rating-stars"
                                                >Sterne</Label
                                            >
                                            <div
                                                id="rating-stars"
                                                class="star-wrapper"
                                            >
                                                {#each [1, 2, 3, 4, 5] as star (star)}
                                                    <button
                                                        type="button"
                                                        class={cn(
                                                            `star-button s${star}`,
                                                            star <=
                                                                ratingStars &&
                                                                'active',
                                                        )}
                                                        onclick={() =>
                                                            (ratingStars =
                                                                star)}
                                                        aria-label={starLabel(
                                                            star,
                                                        )}
                                                        aria-pressed={star ===
                                                            ratingStars}
                                                    >
                                                        <Star class="size-12" />
                                                    </button>
                                                {/each}
                                            </div>
                                            <p
                                                class="text-sm text-muted-foreground"
                                            >
                                                {starLabel(ratingStars)}
                                            </p>
                                            <InputError
                                                message={errors.stars}
                                            />
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="content"
                                                >Bewertung</Label
                                            >
                                            <Textarea
                                                id="content"
                                                name="content"
                                                rows={5}
                                                required
                                                placeholder="Wie zufrieden sind Sie mit dem Produkt?"
                                            />
                                            <InputError
                                                message={errors.content}
                                            />
                                        </div>

                                        <Button
                                            type="submit"
                                            class="w-full bg-primary text-white hover:bg-primary/90"
                                            disabled={processing}
                                        >
                                            Bewertung senden
                                        </Button>
                                    {/snippet}
                                </Form>
                            </div>
                        {/if}
                    </div>
                {/if}
            </div>
        </div>
    </main>

    <SortimentShowcase title="Ähnliche Produkte" products={similarProducts} />

    <AppFooter />
</div>

<style>
    .star-wrapper {
        display: inline-flex;
        direction: rtl;
    }

    .star-button {
        margin: 4px;
        color: #d1d5db;
        text-decoration: none;
        transition: all 0.5s;
    }

    .star-button:hover {
        color: gold;
        transform: scale(1.3);
    }

    .star-button:hover ~ .star-button {
        color: gold;
    }

    .star-button.active {
        color: gold;
    }
</style>
