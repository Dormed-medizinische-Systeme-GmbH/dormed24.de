import { fireEvent, render, screen } from '@testing-library/svelte';
import { tick } from 'svelte';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import RateProductButton from '@/components/RateProductButton.svelte';

const { fetchJsonMock } = vi.hoisted(() => ({ fetchJsonMock: vi.fn() }));

vi.mock('@/lib/http', () => ({ fetchJson: fetchJsonMock }));

const props = { productId: 3, productName: 'Therapieliege' };

describe('RateProductButton', () => {
    beforeEach(() => {
        fetchJsonMock.mockReset();
    });

    it('renders nothing for items that cannot be rated', () => {
        const { container } = render(RateProductButton, {
            props: { ...props, rating: null },
        });

        // Svelte lässt nur einen Kommentar-Anker zurück.
        expect(container.textContent).toBe('');
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });

    it('marks already rated items', () => {
        render(RateProductButton, { props: { ...props, rating: 'rated' } });

        expect(screen.getByText('Bewertet')).toBeInTheDocument();
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });

    it('opens and closes the inline rating form', async () => {
        render(RateProductButton, { props: { ...props, rating: 'rateable' } });

        await fireEvent.click(screen.getByRole('button', { name: 'Bewerten' }));
        expect(screen.getByText('Therapieliege bewerten')).toBeInTheDocument();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Abbrechen' }),
        );
        expect(
            screen.queryByText('Therapieliege bewerten'),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Bewerten' }),
        ).toBeInTheDocument();
    });

    it('switches to "Bewertet" after a successful submission', async () => {
        fetchJsonMock.mockResolvedValue({
            ok: true,
            status: 201,
            data: { rating: { id: 1, stars: 5, content: 'Top Liege.' } },
        });
        render(RateProductButton, { props: { ...props, rating: 'rateable' } });

        await fireEvent.click(screen.getByRole('button', { name: 'Bewerten' }));
        await fireEvent.input(screen.getByLabelText('Bewertung'), {
            target: { value: 'Top Liege.' },
        });
        await fireEvent.submit(
            screen.getByRole('button', { name: 'Bewertung senden' }),
        );
        await tick();

        expect(screen.getByText('Bewertet')).toBeInTheDocument();
        expect(
            screen.queryByText('Therapieliege bewerten'),
        ).not.toBeInTheDocument();
    });
});
