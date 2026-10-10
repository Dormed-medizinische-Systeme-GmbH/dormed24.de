import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import VariantSwitcher from '@/components/VariantSwitcher.svelte';
import { EDIT_VARIANTS } from '@/lib/productEdit';

function setup(current = 'A') {
    const onselect = vi.fn();
    render(VariantSwitcher, {
        props: { options: EDIT_VARIANTS, current, onselect },
    });

    return onselect;
}

describe('VariantSwitcher', () => {
    it('shows the current variant and all choices right away', () => {
        setup('C');

        expect(screen.getByText('Abschnitte')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /^Variante C/ }),
        ).toHaveAttribute('aria-current', 'true');
        expect(
            screen.getByRole('button', { name: /^Variante A/ }),
        ).toHaveAttribute('aria-current', 'false');
        expect(
            screen.getAllByRole('button', { name: /^Variante / }),
        ).toHaveLength(5);
    });

    it('selects a variant by its letter', async () => {
        const onselect = setup('A');

        await fireEvent.click(
            screen.getByRole('button', { name: /^Variante D/ }),
        );

        expect(onselect).toHaveBeenCalledWith('D');
    });

    it('steps forward and backward and wraps around', async () => {
        const onselect = setup('E');
        await fireEvent.click(
            screen.getByRole('button', { name: 'Nächste Variante' }),
        );
        expect(onselect).toHaveBeenLastCalledWith('A');

        await fireEvent.click(
            screen.getByRole('button', { name: 'Vorherige Variante' }),
        );
        expect(onselect).toHaveBeenLastCalledWith('D');
    });
});
