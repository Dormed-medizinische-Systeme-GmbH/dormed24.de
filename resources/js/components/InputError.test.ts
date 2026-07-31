import { render, screen } from '@testing-library/svelte';
import { describe, expect, it } from 'vitest';
import InputError from '@/components/InputError.svelte';

describe('InputError', () => {
    it('renders nothing when there is no message', () => {
        const { container } = render(InputError);

        expect(container.querySelector('div')).not.toBeInTheDocument();
    });

    it('renders the message when provided', () => {
        render(InputError, { props: { message: 'Pflichtfeld.' } });

        expect(screen.getByText('Pflichtfeld.')).toBeInTheDocument();
    });

    it('applies the given class to the wrapper', () => {
        render(InputError, {
            props: { message: 'Pflichtfeld.', class: 'mt-2' },
        });

        expect(screen.getByText('Pflichtfeld.').parentElement).toHaveClass(
            'mt-2',
        );
    });
});
