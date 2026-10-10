<script lang="ts">
    import EditSection from '@/components/product-edit/EditSection.svelte';
    import ProductImagesEditor from '@/components/product-edit/ProductImagesEditor.svelte';
    import ProductRatingsList from '@/components/product-edit/ProductRatingsList.svelte';
    import ProductVariantsEditor from '@/components/product-edit/ProductVariantsEditor.svelte';
    import { cn } from '@/lib/utils';
    import type { ProductEditLayoutProps } from '@/types';

    let { product, ratings, fields, onsubmit }: ProductEditLayoutProps =
        $props();

    const sections = $derived([
        { id: 'allgemein', title: 'Allgemein' },
        { id: 'preis-zuordnung', title: 'Preis & Zuordnung' },
        {
            id: 'technische-daten',
            title: 'Technische Daten',
        },
        {
            id: 'bilder',
            title: 'Bilder',
            count: `${product.images.length}/5`,
        },
        {
            id: 'variationen',
            title: 'Variationen',
            count: String(product.variants.length),
        },
        {
            id: 'bewertungen',
            title: 'Bewertungen',
            count: String(ratings.length),
        },
        { id: 'verfuegbarkeit', title: 'Verfügbarkeit' },
    ]);

    let nav = $state<HTMLElement>();
    let activeId = $state('allgemein');

    function goTo(id: string) {
        document
            .getElementById(id)
            ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Scrollspy: Der Inhalt scrollt im Layout-Container, nicht im Fenster.
    $effect(() => {
        const scroller = nav?.closest<HTMLElement>('.overflow-y-auto');

        if (!scroller) {
            return;
        }

        const update = () => {
            const top = scroller.getBoundingClientRect().top;
            let current = sections[0].id;

            for (const { id } of sections) {
                const el = document.getElementById(id);

                if (el && el.getBoundingClientRect().top - top < 120) {
                    current = id;
                }
            }

            activeId = current;
        };

        scroller.addEventListener('scroll', update, { passive: true });
        update();

        return () => scroller.removeEventListener('scroll', update);
    });
</script>

<!-- Variante C: Sprungnavigation links, Abschnitte mit Überschrift darüber -->
<div
    class="mx-auto grid w-full max-w-4xl gap-10 lg:max-w-5xl lg:grid-cols-[12rem_minmax(0,1fr)]"
>
    <nav
        bind:this={nav}
        aria-label="Abschnitte"
        class="sticky top-4 hidden flex-col gap-0.5 self-start lg:flex"
    >
        {#each sections as section (section.id)}
            <button
                type="button"
                onclick={() => goTo(section.id)}
                aria-current={activeId === section.id ? 'location' : undefined}
                class={cn(
                    'flex items-center justify-between rounded-md px-2.5 py-1.5 text-left text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground',
                    activeId === section.id &&
                        'bg-accent font-medium text-foreground',
                )}
            >
                {section.title}
                {#if section.count}
                    <span class="text-xs tabular-nums">{section.count}</span>
                {/if}
            </button>
        {/each}
    </nav>

    <div class="min-w-0">
        <EditSection
            id="allgemein"
            title="Allgemein"
            description="Name und Beschreibung des Produkts."
        >
            <form {onsubmit} class="flex flex-col gap-6">
                {@render fields.name()}
                {@render fields.description()}
            </form>
        </EditSection>

        <EditSection
            id="preis-zuordnung"
            title="Preis & Zuordnung"
            description="Grundpreis, Kategorie, Hersteller und Fachrichtungen für die Filter im Shop."
        >
            <form {onsubmit} class="flex flex-col gap-6">
                <div class="grid gap-6 sm:grid-cols-2">
                    {@render fields.price()}
                    {@render fields.manufacturer()}
                </div>
                {@render fields.category()}
                {@render fields.specialties()}
            </form>
        </EditSection>

        <EditSection
            id="technische-daten"
            title="Technische Daten"
            description="Erscheint im Shop im Reiter „Technische Daten“. Leere Zeilen werden beim Speichern ignoriert."
        >
            {@render fields.specs(true)}
        </EditSection>

        <EditSection
            id="bilder"
            title="Bilder"
            description="Bis zu 5 Bilder, das erste ist das Hauptbild."
        >
            <ProductImagesEditor
                productId={product.id}
                images={product.images}
                showHeader={false}
            />
        </EditSection>

        <EditSection
            id="variationen"
            title="Variationen"
            description="Optionale Preisvarianten, z.B. Packungsgrößen. Ohne Variationen gilt der Produktpreis."
        >
            <ProductVariantsEditor
                productId={product.id}
                variants={product.variants}
                showHeader={false}
            />
        </EditSection>

        <EditSection
            id="bewertungen"
            title="Bewertungen"
            description="Bewertungen von Käufern sind sofort öffentlich. Hier lassen sich unpassende löschen."
        >
            <ProductRatingsList {ratings} showHeader={false} />
        </EditSection>

        <EditSection
            id="verfuegbarkeit"
            title="Verfügbarkeit"
            description="Steuert, ob das Produkt im Shop bestellbar ist."
        >
            <form {onsubmit} class="flex flex-col gap-6">
                {@render fields.availability()}
                {@render fields.actions()}
            </form>
        </EditSection>
    </div>
</div>
