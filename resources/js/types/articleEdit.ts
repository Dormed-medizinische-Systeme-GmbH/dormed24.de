import type { Snippet } from 'svelte';

/** Die Felder der Magazin-Maske als Snippets; `ArticleFields` besitzt die Bindungen. */
export type ArticleEditFields = {
    /** `true` = großer Schreibfokus-Titel ohne sichtbares Label. */
    title: Snippet<[boolean?]>;
    slug: Snippet;
    teaser: Snippet;
    content: Snippet;
    category: Snippet;
    publishedAt: Snippet;
    /** Gesperrte Platzhalter für später: Titelbild, SEO, Autor/Tags/Verwandtes. */
    cover: Snippet;
    seo: Snippet;
    meta: Snippet;
};

export type ArticleEditLayoutProps = {
    fields: ArticleEditFields;
    errors: Partial<Record<string, string>>;
};

/** Live-Werte des (ungespeicherten) Artikels für die Vorschau. */
export type ArticleEditPreview = {
    title: string;
    teaser: string;
    categoryLabel: string;
    publishedAt: string;
    content: string;
};
