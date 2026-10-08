import ExternalLink from '@lucide/svelte/icons/external-link';
import { fireEvent, render, screen } from '@testing-library/svelte';
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

    it('renders extra links after edit and opens external ones in a new tab', async () => {
        render(RowActions, {
            props: {
                editHref: '/admin/products/3/edit',
                links: [
                    {
                        href: '/produkte/3',
                        label: 'Anzeigen',
                        icon: ExternalLink,
                        external: true,
                    },
                ],
                destroyHref: '/admin/products/3',
            },
        });

        await openMenu();

        const links = screen.getAllByRole('link');
        expect(links.map((link) => link.textContent?.trim())).toEqual([
            'Bearbeiten',
            'Anzeigen',
        ]);
        expect(links[1]).toHaveAttribute('target', '_blank');
    });

    it('offers no delete without a destroy address', async () => {
        render(RowActions, {
            props: {
                links: [
                    {
                        href: '/admin/newsletters/2/content',
                        label: 'Inhalt bearbeiten',
                        icon: ExternalLink,
                    },
                ],
            },
        });

        await openMenu();

        expect(
            screen.getByRole('link', { name: 'Inhalt bearbeiten' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Bearbeiten' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Löschen' }),
        ).not.toBeInTheDocument();
    });
});
