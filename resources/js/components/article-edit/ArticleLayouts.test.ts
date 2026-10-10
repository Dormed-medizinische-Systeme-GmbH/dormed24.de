import { render, screen } from '@testing-library/svelte';
import { createRawSnippet } from 'svelte';
import type { Component } from 'svelte';
import { describe, expect, it, vi } from 'vitest';
import ArticleLayoutFocus from '@/components/article-edit/ArticleLayoutFocus.svelte';
import ArticleLayoutPreview from '@/components/article-edit/ArticleLayoutPreview.svelte';
import ArticleLayoutSections from '@/components/article-edit/ArticleLayoutSections.svelte';
import ArticleLayoutSidebar from '@/components/article-edit/ArticleLayoutSidebar.svelte';
import ArticleLayoutTabs from '@/components/article-edit/ArticleLayoutTabs.svelte';
import { ARTICLE_VARIANTS } from '@/lib/articleEdit';
import type { ArticleEditFields } from '@/types';

vi.mock('@inertiajs/svelte', async (importOriginal) => ({
    ...(await importOriginal<object>()),
    router: { on: vi.fn(() => () => {}) },
}));

const KEYS = [
    'title',
    'slug',
    'teaser',
    'content',
    'category',
    'publishedAt',
    'cover',
    'seo',
    'meta',
] as const;

const fields = Object.fromEntries(
    KEYS.map((key) => [
        key,
        createRawSnippet(() => ({
            render: () => `<div data-field="${key}">${key}</div>`,
        })),
    ]),
) as unknown as ArticleEditFields;

const preview = {
    title: 'Rollator richtig wählen',
    teaser: 'Kurz erklärt.',
    categoryLabel: 'Ratgeber',
    publishedAt: '',
    content: 'Text',
};

const layouts: [string, Component<any>][] = [
    ['A', ArticleLayoutSidebar],
    ['B', ArticleLayoutTabs],
    ['C', ArticleLayoutFocus],
    ['D', ArticleLayoutSections],
    ['E', ArticleLayoutPreview],
];

function keys(container: HTMLElement): string[] {
    return [...container.querySelectorAll('[data-field]')].map(
        (el) => (el as HTMLElement).dataset.field as string,
    );
}

describe('Magazin-Maske – Layout-Varianten', () => {
    it('offers exactly the variants A to E, A first', () => {
        expect(ARTICLE_VARIANTS.map((variant) => variant.key)).toEqual([
            'A',
            'B',
            'C',
            'D',
            'E',
        ]);
    });

    it.each(layouts)('%s places each field exactly once', (_key, Layout) => {
        const { container } = render(Layout, {
            props: { fields, errors: {}, preview },
        });

        for (const key of KEYS) {
            expect(keys(container).filter((k) => k === key)).toHaveLength(1);
        }
    });

    it('B splits the fields over three tabs and marks tabs with errors', () => {
        const { container } = render(ArticleLayoutTabs as Component<any>, {
            props: { fields, errors: { slug: 'Bereits vergeben.' } },
        });

        const panels = [
            ...container.querySelectorAll<HTMLElement>('[role=tabpanel]'),
        ];

        expect(panels.map((panel) => keys(panel))).toEqual([
            ['title', 'teaser', 'content'],
            ['slug', 'category', 'publishedAt'],
            ['cover', 'seo', 'meta'],
        ]);
        expect(
            screen.getByRole('tab', { name: /Veröffentlichung/ }),
        ).toContainElement(screen.getByRole('img', { name: 'Enthält Fehler' }));
        expect(
            screen.getByRole('tab', { name: /^Inhalt/ }),
        ).not.toContainElement(
            screen.queryByRole('img', { name: 'Enthält Fehler' }),
        );
    });

    it('C opens the slug panel when the slug has an error', () => {
        const { container } = render(ArticleLayoutFocus as Component<any>, {
            props: { fields, errors: { slug: 'Bereits vergeben.' } },
        });

        const slugPanel = container
            .querySelector('[data-field="slug"]')
            ?.closest('details');

        expect(slugPanel).toHaveAttribute('open');
    });

    it('D puts every section heading above its fields', () => {
        const { container } = render(ArticleLayoutSections as Component<any>, {
            props: { fields, errors: {} },
        });

        for (const section of container.querySelectorAll('section[id]')) {
            expect(section.className).toContain('flex-col');
            expect(section.children[0].querySelector('h2')).not.toBeNull();
        }
    });

    it('E shows the live preview and marks unscheduled articles as draft', () => {
        render(ArticleLayoutPreview as Component<any>, {
            props: { fields, errors: {}, preview },
        });

        const article = screen.getByTestId('article-preview');

        expect(article).toHaveTextContent('Rollator richtig wählen');
        expect(article).toHaveTextContent('Entwurf');
    });
});
