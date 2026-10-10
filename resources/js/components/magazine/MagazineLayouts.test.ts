import { render, screen } from '@testing-library/svelte';
import type { Component } from 'svelte';
import { describe, expect, it, vi } from 'vitest';
import MagazineLayoutArchive from '@/components/magazine/MagazineLayoutArchive.svelte';
import MagazineLayoutCategories from '@/components/magazine/MagazineLayoutCategories.svelte';
import MagazineLayoutHighlight from '@/components/magazine/MagazineLayoutHighlight.svelte';
import MagazineLayoutList from '@/components/magazine/MagazineLayoutList.svelte';
import MagazineLayoutMosaic from '@/components/magazine/MagazineLayoutMosaic.svelte';
import type { ArticleTeaser } from '@/types';

vi.mock('@inertiajs/svelte', async (importOriginal) => ({
    ...(await importOriginal<object>()),
    router: { on: vi.fn(() => () => {}) },
}));

const articles: ArticleTeaser[] = [
    {
        title: 'Rollator richtig wählen',
        slug: 'rollator-richtig-waehlen',
        teaser: 'Worauf es bei der Kaufberatung ankommt.',
        category: 'Ratgeber',
        published_at: '2026-09-20T10:00:00Z',
    },
    {
        title: 'Neue Hilfsmittel 2026',
        slug: 'neue-hilfsmittel-2026',
        teaser: null,
        category: 'News',
        published_at: '2026-08-15T10:00:00Z',
    },
    {
        title: 'Pflegebett im Überblick',
        slug: 'pflegebett-ueberblick',
        teaser: 'Modelle und Förderung.',
        category: 'Ratgeber',
        published_at: null,
    },
];

const categories = { ratgeber: 'Ratgeber', news: 'News' };

const layouts: [string, Component<any>][] = [
    ['A', MagazineLayoutHighlight],
    ['B', MagazineLayoutList],
    ['C', MagazineLayoutMosaic],
    ['D', MagazineLayoutCategories],
    ['E', MagazineLayoutArchive],
];

const props = { articles, categories, activeCategory: null };

describe('Magazin – Layout-Varianten', () => {
    it.each(layouts)('%s lists every article with a link', (_key, Layout) => {
        render(Layout, { props });

        for (const article of articles) {
            const link = screen.getAllByRole('link', {
                name: new RegExp(article.title),
            })[0];

            expect(link.getAttribute('href')).toContain(article.slug);
        }
    });

    it.each(layouts)('%s shows the category filters', (_key, Layout) => {
        render(Layout, { props: { ...props, activeCategory: 'news' } });

        const nav = screen.getByRole('navigation', { name: 'Kategorien' });

        expect(nav).toHaveTextContent('Alle');
        expect(nav).toHaveTextContent('Ratgeber');
        expect(
            screen.getByRole('link', { name: 'News', current: 'page' }),
        ).toBeInTheDocument();
    });

    it.each(layouts)(
        '%s shows the empty state without articles',
        (_key, Layout) => {
            render(Layout, { props: { ...props, articles: [] } });

            expect(
                screen.getByText('Hier erscheinen bald die ersten Beiträge.'),
            ).toBeInTheDocument();
            expect(
                screen.getByRole('heading', { name: 'Magazin' }),
            ).toBeInTheDocument();
        },
    );

    it('D groups articles by category with the heading above', () => {
        render(MagazineLayoutCategories as Component<any>, { props });

        const ratgeber = screen.getByRole('region', { name: 'Ratgeber' });

        expect(ratgeber).toHaveTextContent('Rollator richtig wählen');
        expect(ratgeber).toHaveTextContent('Pflegebett im Überblick');
        expect(ratgeber).not.toHaveTextContent('Neue Hilfsmittel 2026');
    });

    it('E groups articles by month and keeps undated ones last', () => {
        render(MagazineLayoutArchive as Component<any>, { props });

        const labels = screen
            .getAllByRole('heading', { level: 2 })
            .map((heading) => heading.textContent?.trim());

        expect(labels).toEqual(['September 2026', 'August 2026', 'Ohne Datum']);
    });
});
