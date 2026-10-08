import { fireEvent, render, screen } from '@testing-library/svelte';
import { createRawSnippet } from 'svelte';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import RowActions from '@/components/RowActions.svelte';

const { deleteMock } = vi.hoisted(() => ({ deleteMock: vi.fn() }));

vi.mock('@inertiajs/svelte', () => ({ router: { delete: deleteMock } }));

async function openMenu(): Promise<void> {
    await fireEvent.click(screen.getByRole('button', { name: 'Aktionen' }));
}

describe('RowActions', () => {
    beforeEach(() => {
        deleteMock.mockReset();
    });

    it('links to the edit page and deletes after confirmation', async () => {
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        render(RowActions, {
            props: {
                editHref: '/admin/articles/4/edit',
                destroyHref: '/admin/articles/4',
                confirmMessage: 'Artikel „A“ wirklich löschen?',
            },
        });

        await openMenu();

        const edit = await screen.findByRole('link', { name: 'Bearbeiten' });
        expect(new URL((edit as HTMLAnchorElement).href).pathname).toBe(
            '/admin/articles/4/edit',
        );

        await fireEvent.click(screen.getByRole('button', { name: 'Löschen' }));
        expect(window.confirm).toHaveBeenCalledWith(
            'Artikel „A“ wirklich löschen?',
        );
        expect(deleteMock).toHaveBeenCalledWith('/admin/articles/4');
    });

    it('does not delete when the confirmation is declined', async () => {
        vi.spyOn(window, 'confirm').mockReturnValue(false);
        render(RowActions, {
            props: {
                destroyHref: '/admin/articles/4',
                confirmMessage: 'Sicher?',
            },
        });

        await openMenu();
        await fireEvent.click(
            await screen.findByRole('button', { name: 'Löschen' }),
        );

        expect(deleteMock).not.toHaveBeenCalled();
    });

    it('omits edit without a link and renders extra items', async () => {
        render(RowActions, {
            props: {
                destroyHref: '/admin/newsletters/2',
                confirmMessage: 'Sicher?',
                items: createRawSnippet(() => ({
                    render: () => '<button type="button">Duplizieren</button>',
                })),
            },
        });

        await openMenu();

        expect(
            await screen.findByRole('button', { name: 'Duplizieren' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Bearbeiten' }),
        ).not.toBeInTheDocument();
    });
});
