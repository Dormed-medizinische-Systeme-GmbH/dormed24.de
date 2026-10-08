import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import BlockPalette from './BlockPalette.svelte';

const blocks = [
    { id: 'mj-text', label: 'Text' },
    { id: 'mj-button', label: 'Button' },
    { id: 'mj-2-columns', label: '2 Columns' },
];

function renderPalette() {
    const onDragStart = vi.fn();
    const onDragEnd = vi.fn();
    render(BlockPalette, { props: { blocks, onDragStart, onDragEnd } });

    return { onDragStart, onDragEnd };
}

describe('BlockPalette', () => {
    it('shows the blocks grouped with German names', () => {
        renderPalette();

        expect(
            screen.getByRole('heading', { name: 'Layout' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Inhalt' }),
        ).toBeInTheDocument();
        expect(screen.getByText('2 Spalten')).toBeInTheDocument();
        expect(screen.getByText('Text')).toBeInTheDocument();
        expect(screen.getByText('Button')).toBeInTheDocument();
    });

    it('filters the blocks by the search term', async () => {
        renderPalette();

        await fireEvent.input(screen.getByLabelText('Block suchen'), {
            target: { value: 'butt' },
        });

        expect(screen.getByText('Button')).toBeInTheDocument();
        expect(screen.queryByText('Text')).not.toBeInTheDocument();
        expect(
            screen.queryByRole('heading', { name: 'Layout' }),
        ).not.toBeInTheDocument();
    });

    it('shows a hint when no block matches', async () => {
        renderPalette();

        await fireEvent.input(screen.getByLabelText('Block suchen'), {
            target: { value: 'xyz' },
        });

        expect(
            screen.getByText('Kein Block passt zu „xyz“.'),
        ).toBeInTheDocument();
    });

    it('passes the block to the drag callbacks', async () => {
        const { onDragStart, onDragEnd } = renderPalette();
        const item = screen.getByText('Button').closest('[draggable="true"]')!;

        await fireEvent.dragStart(item);
        await fireEvent.dragEnd(item);

        expect(onDragStart).toHaveBeenCalledOnce();
        expect(onDragStart.mock.calls[0][0]).toEqual({
            id: 'mj-button',
            label: 'Button',
        });
        expect(onDragEnd).toHaveBeenCalledOnce();
    });
});
