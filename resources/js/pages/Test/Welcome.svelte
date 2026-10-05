<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import ArrowLeft from '@lucide/svelte/icons/arrow-left';
    import ArrowRight from '@lucide/svelte/icons/arrow-right';
    import Check from '@lucide/svelte/icons/check';
    import FileCheck from '@lucide/svelte/icons/file-check';
    import ImageIcon from '@lucide/svelte/icons/image';
    import PackageCheck from '@lucide/svelte/icons/package-check';
    import Phone from '@lucide/svelte/icons/phone';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import Truck from '@lucide/svelte/icons/truck';
    import * as ProductController from '@/actions/App/Http/Controllers/ProductController';
    import AppHead from '@/components/AppHead.svelte';
    import ShopHeader from '@/components/ShopHeader.svelte';
    import TestAppFooter from '@/components/TestAppFooter.svelte';
    import { Card, CardContent } from '@/components/ui/card';
    import { formatPrice } from '@/lib/currency';

    const trustItems = [
        {
            icon: PackageCheck,
            label: 'Einfach direkt bestellen',
            text: 'Sortiment einsehen, in den Warenkorb, fertig — ganz ohne Angebotsanfrage.',
        },
        {
            icon: Truck,
            label: 'Lieferung oder Abholung',
            text: 'Sie entscheiden, ob es zu Ihnen kommt oder Sie es bei uns abholen.',
        },
        {
            icon: FileCheck,
            label: 'Inkl. GWE und Einweisung',
            text: 'Messtechnische Kontrolle und Einweisung sind bei uns kein Aufpreis.',
        },
        {
            icon: ShieldCheck,
            label: 'Gewährleistung & geprüfte Qualität',
            text: 'Jedes Gerät durchläuft eine Prüfung, bevor es Ihre Praxis erreicht.',
        },
    ] as const;

    const features = [
        'Diagnostik',
        'Notfallmedizin',
        'Monitoring',
        'Zubehör',
        'Verbrauchsmaterial',
    ] as const;

    const onboardingSteps = [
        {
            title: 'Online registrieren',
            text: 'Erstellen Sie Ihr persönliches Kundenkonto in wenigen Minuten und starten Sie ohne Umwege in die Beschaffung.',
        },
        {
            title: 'Sortiment freischalten',
            text: 'Nach kurzer Prüfung erhalten Sie Zugang zu Ihrem Sortiment und zu den für Sie passenden Konditionen.',
        },
        {
            title: 'Schnell bestellen',
            text: 'Vom Verbrauchsmaterial bis zur Medizintechnik bestellen Sie mit wenigen Klicks und klarer Betreuung.',
        },
    ] as const;

    type ContactInfo = {
        email: string;
        phone: string;
        fax: string;
        phone_href: string;
        fax_href: string;
    };

    type RandomProduct = {
        id: number;
        name: string;
        price: string | null;
        image_url: string | null;
    };

    let {
        randomProductsTitle = 'Entdecken Sie unser Sortiment',
        randomProducts = [],
    }: {
        randomProductsTitle?: string;
        randomProducts?: RandomProduct[];
    } = $props();

    const contact = $derived(page.props.contact as ContactInfo);

    let scrollY = $state(0);
    const heroParallaxOffset = $derived(Math.min(scrollY * 0.22, 130));

    let activeProductIndex = $state(0);
    const activeProduct = $derived(
        randomProducts[activeProductIndex] ?? randomProducts[0],
    );

    $effect(() => {
        if (activeProductIndex >= randomProducts.length) {
            activeProductIndex = 0;
        }
    });

    function showPreviousProduct() {
        if (randomProducts.length === 0) {
            return;
        }

        activeProductIndex =
            activeProductIndex === 0
                ? randomProducts.length - 1
                : activeProductIndex - 1;
    }

    function showNextProduct() {
        if (randomProducts.length === 0) {
            return;
        }

        activeProductIndex =
            activeProductIndex === randomProducts.length - 1
                ? 0
                : activeProductIndex + 1;
    }

    function displayProductPrice(price: string | null): string {
        return price === null ? 'Preis auf Anfrage' : formatPrice(price);
    }

    const siteUrl = $derived(
        typeof window === 'undefined' ? '' : window.location.origin,
    );

    const organizationSchema = $derived.by(() => {
        const schema = {
            '@context': 'https://schema.org',
            '@graph': [
                {
                    '@type': 'Organization',
                    name: 'dormed 24',
                    url: siteUrl || '/',
                    logo: `${siteUrl}/logo.svg`,
                    email: contact.email,
                    telephone: contact.phone,
                },
                {
                    '@type': 'WebSite',
                    name: 'dormed24.de',
                    url: siteUrl || '/',
                },
            ],
        };

        // "<" escaped so setting-supplied content (email/phone) can never
        // close the surrounding script tag early (script-breakout injection).
        return JSON.stringify(schema).replace(/</g, '\\u003c');
    });

    const organizationJsonLdHtml = $derived(
        `<script type="application/ld+json">${organizationSchema}<` +
            `/script>`,
    );
</script>

<svelte:window bind:scrollY />

<AppHead title="Willkommen (Test: Artifact-Redesign)" />

<svelte:head>
    <meta name="robots" content="noindex, nofollow" />
    <!-- eslint-disable-next-line svelte/no-at-html-tags -- JSON-LD; "<" is escaped in organizationSchema -->
    {@html organizationJsonLdHtml}
</svelte:head>

<div class="min-h-screen bg-white">
    <ShopHeader />

    <!-- Hero -->
    <section
        class="relative -mt-16 min-h-[calc(72svh+4rem)] overflow-hidden pt-16 md:-mt-28 md:min-h-[calc(100svh+7rem)] md:pt-28"
    >
        <!--
            Background image fills the section. AVIF (54 KB) → WebP (196 KB) →
            PNG (748 KB): der Browser nimmt das erste Format, das er kann.
            `class="contents"` am <picture>, damit der Wrapper das Layout nicht
            beeinflusst — das <img> bleibt absolut positioniert wie zuvor.
        -->
        <picture class="contents">
            <source srcset="/assets/hero-banner.avif" type="image/avif" />
            <source srcset="/assets/hero-banner.webp" type="image/webp" />
            <img
                src="/assets/hero-banner.png"
                alt=""
                aria-hidden="true"
                width="1920"
                height="1080"
                fetchpriority="high"
                decoding="async"
                class="absolute inset-x-0 -top-20 h-[calc(100%+5rem)] w-full object-cover object-center md:-top-28 md:h-[calc(100%+7rem)]"
                style:transform={`translate3d(0, ${heroParallaxOffset}px, 0)`}
            />
        </picture>
        <!--
            Seitlicher Verlauf statt Text-direkt-auf-Foto: deckendes Navy
            (= --primary / #0d1f44) links, komplett durchsichtig rechts.
            Garantiert Kontrast fuer den Text unabhaengig vom Bildausschnitt,
            ohne einen sichtbaren Kasten/Card-Rand einzufuehren.
        -->
        <div
            class="absolute inset-0 z-[1]"
            aria-hidden="true"
            style="background: linear-gradient(90deg, rgba(13,31,68,.94) 0%, rgba(13,31,68,.85) 32%, rgba(13,31,68,.35) 58%, rgba(13,31,68,0) 78%);"
        ></div>
        <!-- Content -->
        <div
            class="relative z-10 mx-auto flex min-h-[72svh] max-w-7xl items-center px-8 py-16 will-change-transform md:min-h-[calc(100svh-4.5rem)] md:py-20"
            style:transform={`translate3d(0, ${heroParallaxOffset}px, 0)`}
        >
            <div class="max-w-lg">
                <!-- Farbwert #8fc4f0 wie im abgenommenen Artifact-Mockup (heller als --brand-accent, für Lesbarkeit auf dunklem Verlauf) -->
                <p
                    class="mb-2 text-xs font-bold uppercase tracking-[0.14em] text-[#8fc4f0]"
                >
                    Medizintechnik für Praxis · MVZ · Klinik
                </p>
                <h1
                    class="mb-3 text-3xl font-bold leading-tight text-white lg:text-4xl"
                >
                    Wir kümmern uns um Ihre Praxis
                </h1>
                <p class="mb-7 text-base text-white/75">
                    Verlässliche Medizintechnik für Ihre Patientenversorgung
                </p>

                <ul class="mb-9 flex flex-wrap gap-2">
                    {#each features as feature (feature)}
                        <li
                            class="flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-[13px] font-semibold text-white backdrop-blur-sm"
                        >
                            <Check class="size-3.5 shrink-0 text-[#8fc4f0]" />
                            {feature}
                        </li>
                    {/each}
                </ul>

                <a
                    href={contact.phone_href}
                    class="group inline-flex items-center gap-3.5 rounded-full bg-white py-2 pl-2 pr-5 shadow-lg shadow-black/20 transition hover:-translate-y-px hover:shadow-xl"
                >
                    <span
                        class="grid size-10 place-items-center rounded-full bg-brand-accent"
                    >
                        <Phone class="size-[1.1rem] text-white" />
                    </span>
                    <span>
                        <span
                            class="block text-[11px] font-semibold text-muted-foreground"
                        >
                            Telefonisch erreichbar
                        </span>
                        <span class="block text-[17px] font-bold text-primary">
                            {contact.phone}
                        </span>
                    </span>
                </a>
            </div>
        </div>
    </section>

    <!--
        Ersetzt die generische Lorem-Feature-Section
        ("Show your solution's impact on user success") und zieht die
        Trust-Bar-Inhalte (Lieferung/GWE/Gewaehrleistung) hier mit rein -
        eine Sektion statt zwei getrennte. Linksbuendig statt zentriert.
        Graues Sektions-Grau (bg-muted) fuer sauberen Hell/Grau-Wechsel im
        Seitenrhythmus (Hero -> grau -> weiss -> grau -> Footer).
    -->
    <section class="bg-muted">
        <div class="mx-auto max-w-7xl px-4 py-16 lg:px-8 lg:py-24">
            <div class="max-w-2xl">
                <p
                    class="text-xs font-bold uppercase tracking-[0.14em] text-brand-accent"
                >
                    Direkt bestellen
                </p>
                <h2
                    class="mt-3 text-3xl font-bold leading-tight text-primary lg:text-4xl"
                >
                    Medizintechnik ohne Umwege beschaffen
                </h2>
                <p class="mt-4 text-base leading-7 text-muted-foreground">
                    Vom ersten Klick bis zur Einweisung vor Ort: Sie bestellen
                    direkt, ohne Rückfragen-Ping-Pong – und bekommen Geräte, die
                    geprüft und einsatzbereit ankommen.
                </p>
            </div>

            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                {#each trustItems as item (item.label)}
                    <Card class="border-border">
                        <CardContent class="flex flex-col gap-3">
                            <div
                                class="grid size-11 place-items-center rounded-lg bg-brand-accent/10"
                            >
                                <item.icon
                                    class="size-5 text-brand-accent"
                                    strokeWidth={1.75}
                                />
                            </div>
                            <h3 class="text-base font-bold text-foreground">
                                {item.label}
                            </h3>
                            <p class="text-sm leading-6 text-muted-foreground">
                                {item.text}
                            </p>
                        </CardContent>
                    </Card>
                {/each}
            </div>
        </div>
    </section>

    {#if randomProducts.length > 0 && activeProduct}
        <section class="bg-white">
            <div class="mx-auto max-w-7xl px-4 py-12 lg:px-8 lg:py-16">
                <h2
                    class="text-3xl font-bold leading-tight text-primary lg:text-4xl"
                >
                    {randomProductsTitle}
                </h2>

                <div class="mt-10 md:hidden">
                    <Link
                        href={ProductController.show.url(activeProduct)}
                        class="block text-center"
                    >
                        <div
                            class="relative grid aspect-square place-items-center overflow-hidden rounded-xl bg-[#e9e9e9]"
                        >
                            {#if activeProduct.image_url}
                                <img
                                    src={activeProduct.image_url}
                                    alt={activeProduct.name}
                                    class="size-full object-cover object-center"
                                />
                            {:else}
                                <div
                                    class="relative grid size-24 place-items-center rounded-full border border-border bg-[#ededed]"
                                >
                                    <span
                                        class="absolute left-1/2 top-1/2 h-px w-36 -translate-x-1/2 -translate-y-1/2 rotate-45 bg-muted"
                                    ></span>
                                    <span
                                        class="absolute left-1/2 top-1/2 h-px w-36 -translate-x-1/2 -translate-y-1/2 -rotate-45 bg-muted"
                                    ></span>
                                    <ImageIcon
                                        class="relative size-4 text-muted-foreground"
                                    />
                                </div>
                            {/if}
                        </div>

                        <h3 class="mt-5 text-base font-bold text-foreground">
                            {activeProduct.name}
                        </h3>
                        <p class="mt-3 text-2xl font-bold text-foreground">
                            {displayProductPrice(activeProduct.price)}
                        </p>
                    </Link>

                    <div class="mt-12 flex items-center justify-between gap-6">
                        <div class="flex items-center gap-2">
                            {#each randomProducts as product, index (product.id)}
                                <button
                                    type="button"
                                    class={index === activeProductIndex
                                        ? 'h-2 w-10 rounded-full bg-primary'
                                        : 'size-2.5 rounded-full bg-muted'}
                                    aria-label={`${product.name} anzeigen`}
                                    aria-current={index === activeProductIndex}
                                    onclick={() => (activeProductIndex = index)}
                                ></button>
                            {/each}
                        </div>

                        <div class="flex shrink-0 items-center gap-3">
                            <button
                                type="button"
                                class="grid size-12 place-items-center rounded-full border bg-white text-foreground shadow-sm transition hover:bg-muted"
                                aria-label="Vorheriges Produkt"
                                onclick={showPreviousProduct}
                            >
                                <ArrowLeft class="size-5" />
                            </button>
                            <button
                                type="button"
                                class="grid size-12 place-items-center rounded-full border bg-white text-foreground shadow-sm transition hover:bg-muted"
                                aria-label="Nächstes Produkt"
                                onclick={showNextProduct}
                            >
                                <ArrowRight class="size-5" />
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    class="mt-12 hidden gap-x-5 gap-y-10 md:grid md:grid-cols-2 lg:grid-cols-4"
                >
                    {#each randomProducts as product (product.id)}
                        <Link
                            href={ProductController.show.url(product)}
                            class="text-center"
                        >
                            <div
                                class="relative grid aspect-square place-items-center overflow-hidden rounded-xl bg-[#e9e9e9]"
                            >
                                {#if product.image_url}
                                    <img
                                        src={product.image_url}
                                        alt={product.name}
                                        class="size-full object-cover object-center transition duration-300 hover:scale-105"
                                    />
                                {:else}
                                    <div
                                        class="relative grid size-16 place-items-center rounded-full border border-border bg-[#ededed]"
                                    >
                                        <span
                                            class="absolute left-1/2 top-1/2 h-px w-24 -translate-x-1/2 -translate-y-1/2 rotate-45 bg-muted"
                                        ></span>
                                        <span
                                            class="absolute left-1/2 top-1/2 h-px w-24 -translate-x-1/2 -translate-y-1/2 -rotate-45 bg-muted"
                                        ></span>
                                        <ImageIcon
                                            class="relative size-3.5 text-muted-foreground"
                                        />
                                    </div>
                                {/if}
                            </div>

                            <h3
                                class="mt-5 text-base font-bold text-foreground"
                            >
                                {product.name}
                            </h3>
                            <p class="mt-3 text-2xl font-bold text-foreground">
                                {displayProductPrice(product.price)}
                            </p>
                        </Link>
                    {/each}
                </div>
            </div>
        </section>
    {/if}

    <!--
        Onboarding: echte Reihenfolge (registrieren -> freischalten ->
        bestellen). Grosse, transparente Hintergrund-Ziffer statt kleinem
        Kreis-Badge - abgenommene Variante aus dem Artifact-Vergleich,
        1:1 uebernommen. Jetzt letzte Inhaltssektion vor dem Footer.
    -->
    <section class="bg-muted">
        <div class="mx-auto max-w-7xl px-4 py-16 lg:px-8 lg:py-24">
            <div class="max-w-2xl">
                <p
                    class="text-xs font-bold uppercase tracking-[0.14em] text-brand-accent"
                >
                    So einfach geht's
                </p>
                <h2 class="mt-3 text-2xl font-bold text-foreground lg:text-3xl">
                    Einfach registrieren, freischalten lassen und direkt
                    bestellen
                </h2>
            </div>

            <div
                class="mt-10 grid divide-y divide-border overflow-hidden rounded-2xl border border-border bg-background md:grid-cols-3 md:divide-x md:divide-y-0"
            >
                {#each onboardingSteps as step, index (step.title)}
                    <div class="relative overflow-hidden px-7 pb-7 pt-10">
                        <span
                            class="pointer-events-none absolute right-1 top-2 text-[6.5rem] font-extrabold leading-none text-primary/[0.06]"
                            aria-hidden="true"
                        >
                            {index + 1}
                        </span>
                        <p
                            class="relative mb-4 inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-[0.08em] text-brand-accent"
                        >
                            <span class="size-1.5 rounded-full bg-brand-accent"
                            ></span>
                            Schritt {index + 1}
                        </p>
                        <h3 class="relative text-base font-bold text-primary">
                            {step.title}
                        </h3>
                        <p
                            class="relative mt-2.5 text-sm leading-6 text-muted-foreground"
                        >
                            {step.text}
                        </p>
                    </div>
                {/each}
            </div>
        </div>
    </section>

    <TestAppFooter />
</div>
