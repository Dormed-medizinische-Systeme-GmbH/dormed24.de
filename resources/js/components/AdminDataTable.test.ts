import type { ColumnDef } from '@tanstack/table-core';
import { fireEvent, render, screen, within } from '@testing-library/svelte';
import { createRawSnippet, tick } from 'svelte';
import { describe, expect, it, vi } from 'vitest';
import AdminDataTable from '@/components/AdminDataTable.svelte';

const { visitMock } = vi.hoisted(() => ({ visitMock: vi.fn() }));

vi.mock('@inertiajs/svelte', () => ({ router: { visit: visitMock } }));

type Maker = { id: number; name: string; country: string | null };

const makers: Maker[] = [
    { id: 1, name: 'Siemens', country: 'DE' },
    { id: 2, name: 'Canon', country: 'JP' },
    { id: 3, name: 'Esaote', country: null },
];

const columns: ColumnDef<Maker>[] = [
    { accessorKey: 'name', header: 'Name' },
    {
        accessorKey: 'country',
        header: 'Land',
        cell: ({ row }) => row.original.country ?? '—',
    },
];

function renderTable(data: Maker[] = makers) {
    return render(AdminDataTable<Maker>, {
        props: {
            data,
            columns,
            searchFields: (maker: Maker) => [maker.name, maker.country],
            searchPlaceholder: 'Filtern…',
            emptyText: 'Noch nichts angelegt.',
            noMatchText: 'Nichts gefunden.',
        },
    });
}

function names(): string[] {
    const rows = screen.getAllByRole('row').slice(1);

    return rows.map(
        (row) => within(row).getAllByRole('cell')[1].textContent?.trim() ?? '',
    );
}

describe('AdminDataTable', () => {
    it('renders a row per entry with a selection checkbox', () => {
        renderTable();

        expect(names()).toEqual(['Siemens', 'Canon', 'Esaote']);
        expect(
            screen.getAllByRole('checkbox', { name: 'Zeile auswählen' }),
        ).toHaveLength(3);
        expect(screen.getByText('—')).toBeInTheDocument();
    });

    it('searches the given fields ignoring case', async () => {
        renderTable();

        await fireEvent.input(screen.getByLabelText('Suche'), {
            target: { value: 'jp' },
        });

        expect(names()).toEqual(['Canon']);
    });

    it('tells an empty list apart from a search without hits', async () => {
        renderTable([]);
        expect(screen.getByText('Noch nichts angelegt.')).toBeInTheDocument();

        renderTable();
        await fireEvent.input(screen.getAllByLabelText('Suche')[1], {
            target: { value: 'Philips' },
        });
        expect(screen.getByText('Nichts gefunden.')).toBeInTheDocument();
    });

    it('sorts by a column header, ascending then descending', async () => {
        renderTable();
        const header = screen.getByRole('button', { name: /Name/ });

        await fireEvent.click(header);
        expect(names()).toEqual(['Canon', 'Esaote', 'Siemens']);
        expect(header.closest('th')).toHaveAttribute('aria-sort', 'ascending');

        await fireEvent.click(header);
        expect(names()).toEqual(['Siemens', 'Esaote', 'Canon']);
        expect(header.closest('th')).toHaveAttribute('aria-sort', 'descending');
    });

    it('hides and shows columns via the column menu', async () => {
        renderTable();

        await fireEvent.click(screen.getByRole('button', { name: /Spalten/ }));
        await fireEvent.click(
            screen.getByRole('menuitemcheckbox', { name: 'Land' }),
        );

        expect(
            screen.queryByRole('columnheader', { name: /Land/ }),
        ).not.toBeInTheDocument();

        await fireEvent.click(
            screen.getByRole('menuitemcheckbox', { name: 'Land' }),
        );

        expect(
            screen.getByRole('columnheader', { name: /Land/ }),
        ).toBeInTheDocument();
    });

    it('counts selected rows', async () => {
        renderTable();

        await fireEvent.click(
            screen.getByRole('checkbox', { name: 'Alle auswählen' }),
        );
        await tick();

        expect(screen.getByText('3 ausgewählt')).toBeInTheDocument();
    });

    it('renders extra toolbar filters next to the search', () => {
        render(AdminDataTable<Maker>, {
            props: {
                data: makers,
                columns,
                searchFields: (maker: Maker) => [maker.name],
                searchPlaceholder: 'Filtern…',
                emptyText: '',
                toolbar: createRawSnippet(() => ({
                    render: () => '<button type="button">Kategorie</button>',
                })),
            },
        });

        expect(
            screen.getByRole('button', { name: 'Kategorie' }),
        ).toBeInTheDocument();
    });

    it('opens the row link on click and Enter, but not from a checkbox', async () => {
        visitMock.mockReset();
        render(AdminDataTable<Maker>, {
            props: {
                data: makers,
                columns,
                searchPlaceholder: 'Filtern…',
                emptyText: '',
                rowHref: (maker: Maker) => `/admin/makers/${maker.id}`,
                rowLabel: (maker: Maker) => `${maker.name} öffnen`,
            },
        });

        await fireEvent.click(
            screen.getByRole('link', { name: 'Canon öffnen' }),
        );
        await fireEvent.keyDown(
            screen.getByRole('link', { name: 'Esaote öffnen' }),
            { key: 'Enter' },
        );
        await fireEvent.click(
            screen.getAllByRole('checkbox', { name: 'Zeile auswählen' })[0],
        );

        expect(visitMock.mock.calls).toEqual([
            ['/admin/makers/2'],
            ['/admin/makers/3'],
        ]);
    });

    it('submits a server search instead of filtering the loaded rows', async () => {
        const submit = vi.fn();
        render(AdminDataTable<Maker>, {
            props: {
                data: makers,
                columns,
                searchPlaceholder: 'Filtern…',
                emptyText: '',
                serverSearch: { value: 'alt', submit },
            },
        });

        const input = screen.getByLabelText('Suche');
        expect(input).toHaveValue('alt');

        await fireEvent.input(input, { target: { value: ' canon ' } });
        expect(names()).toHaveLength(3);

        await fireEvent.click(screen.getByRole('button', { name: 'Suchen' }));
        expect(submit).toHaveBeenCalledWith('canon');

        await fireEvent.click(
            screen.getByRole('button', { name: 'Zurücksetzen' }),
        );
        expect(submit).toHaveBeenLastCalledWith('');
    });
});
