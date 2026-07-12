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
    import ShopHeaderWithHover from '@/components/ShopHeader-with-hover.svelte';
    import TestAppFooter from '@/components/TestAppFooter.svelte';
    import { Card, CardContent } from '@/components/ui/card';
    import { formatPrice } from '@/lib/currency';

    const trustItems = [
        {
            icon: PackageCheck,
            label: 'Einfach direkt bestellen',
            text: 'Bestellung direkt im Shop – ohne Angebotsschleifen, mit klaren Preisen.',
        },
        {
            icon: Truck,
            label: 'Lieferung oder Abholung',
            text: 'Per DPD zugestellt oder persönlich bei uns abgeholt – wie es passt.',
        },
        {
            icon: FileCheck,
            label: 'inkl. GWE und Einweisung',
            text: 'Video-Einweisung oder Einweisung vor Ort – Sie starten nicht bei null.',
        },
        {
            icon: ShieldCheck,
            label: 'Gewährleistung und geprüfte Qualität',
            text: 'Jedes Gerät mit Gewährleistung – geprüfte Qualität statt Blindkauf.',
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

    const highlightProducts = [
        {
            title: 'Meone Finger-Pulsoximeter',
            price: '46,41 €*',
            summary:
                'USB-C aufladbar, OLED-Display, Spot-Check oder kontinuierliche Überwachung.',
        },
        {
            title: 'BP2 Blutdruckmessgerät mit 1-Kanal-EKG',
            price: '117,81 €*',
            summary:
                'Kompaktes 2-in-1-Gerät mit EKG-Funktion, App-Anbindung und umfangreichem Zubehör.',
        },
        {
            title: 'Wandkasten Metall mit Alarm',
            price: '189,21 €*',
            summary:
                'Robuster Schutz für Defibrillatoren mit akustischem und visuellem Alarmsystem.',
        },
    ] as const;

    const serviceColumns = [
        {
            eyebrow: 'Praxis & Klinik',
            title: 'Vom Erstgerät bis zur Erweiterung',
            text: 'Beschaffen Sie Diagnostik, Monitoring und Zubehör zentral an einem Ort und mit nachvollziehbarer Betreuung.',
        },
        {
            eyebrow: 'Beratung & Einweisung',
            title: 'Nicht nur bestellen, sondern passend auswählen',
            text: 'Für erklärungsbedürftige Technik bleiben Lieferung, Gewährleistung und Einweisung Teil des Gesamtbilds.',
        },
        {
            eyebrow: 'Schnelle Wege',
            title: 'Abholung oder Lieferung, je nach Einsatz',
            text: 'Ob kurzfristiger Bedarf oder geplante Ausstattung: die Shop-Oberfläche soll auf reale Einkaufsabläufe einzahlen.',
        },
    ] as const;

    const assortmentPillars = [
        'Verlässliche Medizintechnik für Praxis, MVZ und Klinik',
        'Kompakte Geräte für Alltag, Hausbesuch und Notfall',
        'Zubehör und Verbrauchsmaterial als wiederkehrender Bedarf',
        'Persönlicher Ansprechpartner statt anonymer Bestellstrecke',
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

<AppHead title="Willkommen (Test: Hover-Header)" />

<svelte:head>
    <meta name="robots" content="noindex, nofollow" />
    <!-- eslint-disable-next-line svelte/no-at-html-tags -- JSON-LD; "<" is escaped in organizationSchema -->
    {@html organizationJsonLdHtml}
</svelte:head>

<div class="min-h-screen bg-white">
    <ShopHeaderWithHover />

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

                <ul class="mb-10 flex flex-col gap-2.5">
                    {#each features as feature (feature)}
                        <li
                            class="flex items-center gap-2.5 font-semibold text-white"
                        >
                            <Check class="size-4 shrink-0 text-[#8fc4f0]" />
                            {feature}
                        </li>
                    {/each}
                </ul>

                <a
                    href={contact.phone_href}
                    class="group inline-flex items-center gap-4 rounded-xl bg-white/10 px-5 py-3.5 backdrop-blur-sm transition hover:bg-white/20"
                >
                    <div
                        class="rounded-full bg-white/20 p-2.5 transition group-hover:bg-white/30"
                    >
                        <Phone class="size-5 text-white" />
                    </div>
                    <span
                        class="hidden text-2xl font-bold tracking-wide text-white lg:text-3xl"
                    >
                        02301 – 188/600
                    </span>
                    <span
                        class="text-2xl font-bold tracking-wide text-white lg:text-3xl"
                    >
                        {contact.phone}
                    </span>
                </a>
            </div>
        </div>
    </section>

    <!--
        TEST-DESIGN: ersetzt die generische Lorem-Feature-Section
        ("Show your solution's impact on user success") und zieht die
        Trust-Bar-Inhalte (Lieferung/GWE/Gewaehrleistung) hier mit rein,
        wie vom User gewuenscht - eine Sektion statt zwei getrennte.
        Linksbuendig statt zentriert, damit es nicht wie eine generische
        Marketing-Hero-Kachel wirkt.
    -->
    <section class="bg-background">
        <div class="mx-auto max-w-7xl px-4 py-16 lg:px-8 lg:py-24">
            <div class="max-w-2xl">
                <p
                    class="text-xs font-bold uppercase tracking-[0.14em] text-brand-accent"
                >
                    Rundum-Service
                </p>
                <h2
                    class="mt-3 text-3xl font-bold leading-tight text-foreground lg:text-4xl"
                >
                    Nicht nur verkauft — eingerichtet, eingewiesen, betreut.
                </h2>
                <p class="mt-4 text-base leading-7 text-muted-foreground">
                    Jede Bestellung endet nicht an der Haustür: Lieferung oder
                    Abholung, Gewährleistung und Einweisung gehören für uns zum
                    Gerät dazu.
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

    <!--
        Onboarding: echte Reihenfolge (registrieren -> freischalten ->
        bestellen). Grosse, transparente Hintergrund-Ziffer statt kleinem
        Kreis-Badge - abgenommene Variante aus dem Artifact-Vergleich,
        1:1 uebernommen.
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

    {#if randomProducts.length > 0 && activeProduct}
        <section class="bg-white">
            <div class="mx-auto max-w-7xl px-4 py-12 lg:px-8 lg:py-16">
                <h2
                    class="text-4xl font-bold leading-tight text-foreground md:text-center lg:text-5xl"
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

    <section class="bg-white">
        <div class="mx-auto max-w-7xl px-4 py-12 lg:px-8 lg:py-16">
            <div class="mb-8 flex items-end justify-between gap-6">
                <div class="max-w-3xl">
                    <h2 class="text-2xl font-bold text-foreground lg:text-3xl">
                        Neu bei dormed24
                    </h2>
                    <p
                        class="mt-3 text-sm leading-6 text-muted-foreground lg:text-base"
                    >
                        Im aktuellen Live-Shop stehen kompakte Geräte für
                        Diagnostik und Notfallversorgung sichtbar im
                        Vordergrund. Dieser Bereich greift das auf, ohne vom
                        restlichen Shop-Design wegzulaufen.
                    </p>
                </div>
                <Link
                    href={ProductController.index.url()}
                    class="hidden shrink-0 text-sm font-semibold text-brand-accent hover:underline lg:inline-flex lg:items-center lg:gap-2"
                >
                    Alle Produkte ansehen
                    <ArrowRight class="size-4" />
                </Link>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                {#each highlightProducts as product (product.title)}
                    <article
                        class="rounded-xl border bg-white p-6 shadow-sm transition hover:shadow-md"
                    >
                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-brand-accent"
                        >
                            Produkthighlight
                        </p>
                        <h3 class="mt-3 text-lg font-semibold text-foreground">
                            {product.title}
                        </h3>
                        <p class="mt-3 text-sm leading-6 text-muted-foreground">
                            {product.summary}
                        </p>
                        <div
                            class="mt-5 flex items-center justify-between gap-4 border-t pt-4"
                        >
                            <span
                                class="text-sm font-semibold text-brand-secondary"
                                >{product.price}</span
                            >
                            <Link
                                href={ProductController.index.url()}
                                class="text-sm font-semibold text-brand-accent hover:underline"
                            >
                                Zum Sortiment
                            </Link>
                        </div>
                    </article>
                {/each}
            </div>

            <div class="mt-6 lg:hidden">
                <Link
                    href={ProductController.index.url()}
                    class="inline-flex items-center gap-2 text-sm font-semibold text-brand-accent hover:underline"
                >
                    Alle Produkte ansehen
                    <ArrowRight class="size-4" />
                </Link>
            </div>
        </div>
    </section>

    <section class="bg-muted">
        <div class="mx-auto max-w-7xl px-4 py-12 lg:px-8 lg:py-16">
            <div
                class="grid gap-8 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]"
            >
                <div>
                    <h2 class="text-2xl font-bold text-foreground lg:text-3xl">
                        Beschaffung für Praxis, MVZ und Klinik
                    </h2>
                    <p
                        class="mt-3 text-sm leading-6 text-muted-foreground lg:text-base"
                    >
                        Statt einer komplett eigenen Landingpage-Ästhetik
                        bekommt der Shop hier eher denselben nüchternen Ton wie
                        die anderen Seiten: klare Informationen, erkennbare
                        Vorteile und direkte Wege in Sortiment oder Kontakt.
                    </p>

                    <div class="mt-6 grid gap-3">
                        {#each assortmentPillars as pillar (pillar)}
                            <div
                                class="rounded-lg border bg-white px-4 py-3 text-sm text-muted-foreground shadow-sm"
                            >
                                {pillar}
                            </div>
                        {/each}
                    </div>
                </div>

                <div class="rounded-xl border bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-foreground">
                        Beratung und schnelle Wege
                    </h3>
                    <div class="mt-5 space-y-5">
                        {#each serviceColumns as column (column.title)}
                            <div
                                class="border-b pb-5 last:border-b-0 last:pb-0"
                            >
                                <p
                                    class="text-sm font-semibold text-brand-secondary"
                                >
                                    {column.title}
                                </p>
                                <p
                                    class="mt-2 text-sm leading-6 text-muted-foreground"
                                >
                                    {column.text}
                                </p>
                            </div>
                        {/each}
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <Link
                            href={ProductController.index.url()}
                            class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary/90"
                        >
                            Jetzt shoppen
                            <ArrowRight class="size-4" />
                        </Link>
                        <Link
                            href="/kontakt"
                            class="inline-flex items-center gap-2 rounded-lg border bg-white px-4 py-2.5 text-sm font-semibold text-brand-secondary hover:text-brand-accent"
                        >
                            Beratung anfragen
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <TestAppFooter />
</div>
