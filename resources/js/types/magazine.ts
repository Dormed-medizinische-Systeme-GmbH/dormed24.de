export type ArticleTeaser = {
    title: string;
    slug: string;
    teaser: string | null;
    category: string;
    published_at: string | null;
};

/** Gemeinsame Props aller Layout-Varianten der Magazin-Übersicht. */
export type MagazineLayoutProps = {
    articles: ArticleTeaser[];
    categories: Record<string, string>;
    activeCategory: string | null;
};
