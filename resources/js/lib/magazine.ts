import type { ArticleTeaser } from '@/types';

/** Layout-Varianten der Magazin-Übersicht, siehe lib/layoutVariants.ts. */
export const MAGAZINE_VARIANTS = [
    { key: 'A', label: 'Highlight (Standard)' },
    { key: 'B', label: 'Liste mit Filter-Leiste' },
    { key: 'C', label: 'Mosaik' },
    { key: 'D', label: 'Nach Kategorie' },
    { key: 'E', label: 'Archiv nach Monat' },
] as const;

export type ArticleGroup = { label: string; articles: ArticleTeaser[] };

function groupBy(
    articles: ArticleTeaser[],
    labelOf: (article: ArticleTeaser) => string,
): ArticleGroup[] {
    const groups = new Map<string, ArticleTeaser[]>();

    for (const article of articles) {
        const label = labelOf(article);
        groups.set(label, [...(groups.get(label) ?? []), article]);
    }

    return [...groups].map(([label, items]) => ({ label, articles: items }));
}

/** Gruppiert nach Kategorie in der Reihenfolge des ersten Auftretens. */
export function groupByCategory(articles: ArticleTeaser[]): ArticleGroup[] {
    return groupBy(articles, (article) => article.category);
}

/** Gruppiert nach Monat („September 2026“), Artikel ohne Datum zuletzt. */
export function groupByMonth(articles: ArticleTeaser[]): ArticleGroup[] {
    const groups = groupBy(articles, (article) =>
        article.published_at
            ? new Date(article.published_at).toLocaleDateString('de-DE', {
                  month: 'long',
                  year: 'numeric',
              })
            : 'Ohne Datum',
    );

    return [
        ...groups.filter((group) => group.label !== 'Ohne Datum'),
        ...groups.filter((group) => group.label === 'Ohne Datum'),
    ];
}
