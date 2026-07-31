import { render, screen } from '@testing-library/svelte';
import { describe, expect, it } from 'vitest';
import Heading from '@/components/Heading.svelte';

describe('Heading', () => {
    it('renders the title as a heading', () => {
        render(Heading, { props: { title: 'Adressen' } });

        expect(
            screen.getByRole('heading', { name: 'Adressen' }),
        ).toBeInTheDocument();
    });

    it('renders the description when provided', () => {
        render(Heading, {
            props: { title: 'Adressen', description: 'Deine Adressen' },
        });

        expect(screen.getByText('Deine Adressen')).toBeInTheDocument();
    });

    it('omits the description paragraph when none is provided', () => {
        render(Heading, { props: { title: 'Adressen' } });

        expect(screen.queryByText('Deine Adressen')).not.toBeInTheDocument();
    });

    it('uses larger heading styles for the default variant', () => {
        render(Heading, { props: { title: 'Adressen' } });

        expect(screen.getByRole('heading')).toHaveClass('text-xl');
    });

    it('uses compact heading styles for the small variant', () => {
        render(Heading, {
            props: { title: 'Adressen', variant: 'small' },
        });

        expect(screen.getByRole('heading')).toHaveClass('text-base');
    });
});
