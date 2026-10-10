import { render, screen } from '@testing-library/svelte';
import { createRawSnippet } from 'svelte';
import type { Component } from 'svelte';
import { describe, expect, it, vi } from 'vitest';
import SocialPostLayoutColumns from '@/components/social-post-edit/SocialPostLayoutColumns.svelte';
import SocialPostLayoutComposer from '@/components/social-post-edit/SocialPostLayoutComposer.svelte';
import SocialPostLayoutPreview from '@/components/social-post-edit/SocialPostLayoutPreview.svelte';
import SocialPostLayoutSingle from '@/components/social-post-edit/SocialPostLayoutSingle.svelte';
import SocialPostLayoutTabs from '@/components/social-post-edit/SocialPostLayoutTabs.svelte';
import { SOCIAL_POST_VARIANTS } from '@/lib/socialPostEdit';
import type { SocialPostEditFields } from '@/types';

vi.mock('@inertiajs/svelte', async (importOriginal) => ({
    ...(await importOriginal<object>()),
    router: { on: vi.fn(() => () => {}) },
}));

const KEYS = [
    'title',
    'content',
    'platforms',
    'scheduledAt',
    'media',
    'link',
    'hashtags',
    'linked',
    'platformTexts',
] as const;

const fields = Object.fromEntries(
    KEYS.map((key) => [
        key,
        createRawSnippet(() => ({
            render: () => `<div data-field="${key}">${key}</div>`,
        })),
    ]),
) as unknown as SocialPostEditFields;

const preview = {
    content: 'Herbstaktion ist da!',
    platforms: ['linkedin', 'facebook'],
    platformLabels: { linkedin: 'LinkedIn', facebook: 'Facebook', x: 'X' },
    maxContentLength: 10,
};

const layouts: [string, Component<any>][] = [
    ['A', SocialPostLayoutSingle],
    ['B', SocialPostLayoutColumns],
    ['C', SocialPostLayoutTabs],
    ['D', SocialPostLayoutPreview],
    ['E', SocialPostLayoutComposer],
];

function keys(container: HTMLElement): string[] {
    return [...container.querySelectorAll('[data-field]')].map(
        (el) => (el as HTMLElement).dataset.field as string,
    );
}

describe('Social-Media-Maske – Layout-Varianten', () => {
    it('offers exactly the variants A to E, A first', () => {
        expect(SOCIAL_POST_VARIANTS.map((variant) => variant.key)).toEqual([
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

    it('C marks the publishing tab when platforms have an error', () => {
        const { container } = render(SocialPostLayoutTabs as Component<any>, {
            props: { fields, errors: { 'platforms.0': 'Ungültig.' } },
        });

        const panels = [
            ...container.querySelectorAll<HTMLElement>('[role=tabpanel]'),
        ];

        expect(panels.map((panel) => keys(panel))).toEqual([
            ['title', 'content'],
            ['platforms', 'scheduledAt'],
            ['media', 'link', 'hashtags', 'linked', 'platformTexts'],
        ]);
        expect(
            screen.getByRole('tab', { name: /Veröffentlichung/ }),
        ).toContainElement(screen.getByRole('img', { name: 'Enthält Fehler' }));
    });

    it('D shows one preview per selected platform with a character counter', () => {
        render(SocialPostLayoutPreview as Component<any>, {
            props: { fields, errors: {}, preview },
        });

        const linkedin = screen.getByRole('article', {
            name: 'Vorschau LinkedIn',
        });

        expect(linkedin).toHaveTextContent('Herbstaktion ist da!');
        expect(linkedin).toHaveTextContent('20 / 10');
        expect(
            screen.getByRole('article', { name: 'Vorschau Facebook' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('article', { name: 'Vorschau X' }),
        ).toBeNull();
    });

    it('D explains the empty state without platforms', () => {
        render(SocialPostLayoutPreview as Component<any>, {
            props: {
                fields,
                errors: {},
                preview: { ...preview, platforms: [] },
            },
        });

        expect(
            screen.getByText(/Wähle mindestens eine Plattform/),
        ).toBeInTheDocument();
    });
});
