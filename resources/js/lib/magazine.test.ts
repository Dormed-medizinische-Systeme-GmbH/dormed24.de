import { describe, expect, it } from 'vitest';
import {
    groupByCategory,
    groupByMonth,
    MAGAZINE_VARIANTS,
} from '@/lib/magazine';
import type { ArticleTeaser } from '@/types';

const article = (
    slug: string,
    category: string,
    published_at: string | null,
): ArticleTeaser => ({
    title: slug,
    slug,
    teaser: null,
    category,
    published_at,
});

const articles = [
    article('a', 'Ratgeber', '2026-09-20T10:00:00Z'),
    article('b', 'News', '2026-09-12T10:00:00Z'),
    article('c', 'Ratgeber', '2026-08-15T10:00:00Z'),
    article('d', 'News', null),
];

describe('magazine helpers', () => {
    it('offers exactly the variants A to E, A first', () => {
        expect(MAGAZINE_VARIANTS.map((variant) => variant.key)).toEqual([
            'A',
            'B',
            'C',
            'D',
            'E',
        ]);
    });

    it('groups by category in order of first appearance', () => {
        const groups = groupByCategory(articles);

        expect(groups.map((group) => group.label)).toEqual([
            'Ratgeber',
            'News',
        ]);
        expect(groups[0].articles.map((a) => a.slug)).toEqual(['a', 'c']);
        expect(groups[1].articles.map((a) => a.slug)).toEqual(['b', 'd']);
    });

    it('groups by month and puts undated articles last', () => {
        const groups = groupByMonth(articles);

        expect(groups.map((group) => group.label)).toEqual([
            'September 2026',
            'August 2026',
            'Ohne Datum',
        ]);
        expect(groups[0].articles.map((a) => a.slug)).toEqual(['a', 'b']);
        expect(groups[2].articles.map((a) => a.slug)).toEqual(['d']);
    });

    it('returns no groups for no articles', () => {
        expect(groupByCategory([])).toEqual([]);
        expect(groupByMonth([])).toEqual([]);
    });
});
