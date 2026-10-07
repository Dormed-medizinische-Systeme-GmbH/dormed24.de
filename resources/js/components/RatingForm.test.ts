import { fireEvent, render, screen } from '@testing-library/svelte';
import { tick } from 'svelte';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import RatingForm from '@/components/RatingForm.svelte';

const { fetchJsonMock } = vi.hoisted(() => ({ fetchJsonMock: vi.fn() }));

vi.mock('@/lib/http', () => ({ fetchJson: fetchJsonMock }));

async function fillAndSubmit(): Promise<void> {
    await fireEvent.click(screen.getByRole('radio', { name: /^4 von 5/ }));
    await fireEvent.input(screen.getByLabelText('Bewertung'), {
        target: { value: 'Sehr gutes Gerät.' },
    });
    await fireEvent.submit(
        screen.getByRole('button', { name: 'Bewertung senden' }),
    );
    await tick();
}

describe('RatingForm', () => {
    beforeEach(() => {
        fetchJsonMock.mockReset();
    });

    it('posts stars and content to the product rating route and reports the rating', async () => {
        const rating = { id: 7, stars: 4, content: 'Sehr gutes Gerät.' };
        fetchJsonMock.mockResolvedValue({
            ok: true,
            status: 201,
            data: { rating },
        });
        const onSubmitted = vi.fn();
        render(RatingForm, { props: { productId: 12, onSubmitted } });

        await fillAndSubmit();

        expect(fetchJsonMock).toHaveBeenCalledWith('/products/12/ratings', {
            method: 'POST',
            body: { stars: 4, content: 'Sehr gutes Gerät.' },
        });
        expect(onSubmitted).toHaveBeenCalledWith(rating);
        expect(screen.getByLabelText('Bewertung')).toHaveValue('');
    });

    it('shows the first validation message per field', async () => {
        fetchJsonMock.mockResolvedValue({
            ok: false,
            status: 422,
            data: {
                errors: {
                    content: ['Zu kurz.', 'Zweite Meldung.'],
                    rating: ['Sie haben dieses Produkt bereits bewertet.'],
                },
            },
        });
        const onSubmitted = vi.fn();
        render(RatingForm, { props: { productId: 12, onSubmitted } });

        await fillAndSubmit();

        expect(screen.getByText('Zu kurz.')).toBeInTheDocument();
        expect(screen.queryByText('Zweite Meldung.')).not.toBeInTheDocument();
        expect(
            screen.getByText('Sie haben dieses Produkt bereits bewertet.'),
        ).toBeInTheDocument();
        expect(onSubmitted).not.toHaveBeenCalled();
    });

    it.each([
        [401, /Sitzung ist abgelaufen/],
        [419, /Sitzung ist abgelaufen/],
        [403, /nur Produkte aus bezahlten Bestellungen/],
        [500, /konnte nicht gespeichert werden/],
    ])('explains HTTP %i', async (status, message) => {
        fetchJsonMock.mockResolvedValue({ ok: false, status, data: {} });
        render(RatingForm, { props: { productId: 12, onSubmitted: vi.fn() } });

        await fillAndSubmit();

        expect(screen.getByText(message)).toBeInTheDocument();
    });

    it('disables the submit button while sending', async () => {
        let resolve!: (value: unknown) => void;
        fetchJsonMock.mockReturnValue(new Promise((r) => (resolve = r)));
        render(RatingForm, { props: { productId: 12, onSubmitted: vi.fn() } });

        await fillAndSubmit();

        expect(
            screen.getByRole('button', { name: 'Wird gesendet …' }),
        ).toBeDisabled();

        resolve({ ok: false, status: 500, data: {} });
        await tick();
        await tick();

        expect(
            screen.getByRole('button', { name: 'Bewertung senden' }),
        ).toBeEnabled();
    });
});
