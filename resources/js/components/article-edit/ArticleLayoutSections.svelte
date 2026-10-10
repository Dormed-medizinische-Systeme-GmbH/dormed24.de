<script lang="ts">
    import EditSection from '@/components/EditSection.svelte';
    import SectionNav from '@/components/SectionNav.svelte';
    import type { ArticleEditLayoutProps } from '@/types';

    let { fields }: ArticleEditLayoutProps = $props();

    const sections = [
        { id: 'inhalt', title: 'Inhalt' },
        { id: 'veroeffentlichung', title: 'Veröffentlichung' },
        { id: 'medien-seo', title: 'Titelbild & SEO' },
        { id: 'weitere-angaben', title: 'Weitere Angaben' },
    ];
</script>

<!-- Variante D: Sprungnavigation, Abschnitte mit Überschrift darüber -->
<div class="grid w-full gap-10 lg:grid-cols-[12rem_minmax(0,1fr)]">
    <SectionNav {sections} />

    <div class="min-w-0 max-w-3xl">
        <EditSection
            id="inhalt"
            title="Inhalt"
            description="Titel, Kurzbeschreibung und der Artikeltext."
        >
            {@render fields.title()}
            {@render fields.teaser()}
            {@render fields.content()}
        </EditSection>

        <EditSection
            id="veroeffentlichung"
            title="Veröffentlichung"
            description="Adresse, Kategorie und Termin. Ohne Termin bleibt der Artikel ein Entwurf."
        >
            {@render fields.slug()}
            {@render fields.category()}
            {@render fields.publishedAt()}
        </EditSection>

        <EditSection
            id="medien-seo"
            title="Titelbild &amp; SEO"
            description="Vorbereitet, noch ohne Funktion."
        >
            {@render fields.cover()}
            {@render fields.seo()}
        </EditSection>

        <EditSection
            id="weitere-angaben"
            title="Weitere Angaben"
            description="Vorbereitet, noch ohne Funktion."
        >
            {@render fields.meta()}
        </EditSection>
    </div>
</div>
