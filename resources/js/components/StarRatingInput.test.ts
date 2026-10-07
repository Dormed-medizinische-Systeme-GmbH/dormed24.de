import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, it } from 'vitest';
import StarRatingInput from '@/components/StarRatingInput.svelte';

function checkedStar(): HTMLElement {
    return screen.getByRole('radio', { checked: true });
}

describe('StarRatingInput', () => {
    it('preselects five stars with exactly one focusable radio', () => {
        render(StarRatingInput);

        expect(screen.getAllByRole('radio')).toHaveLength(5);
        expect(checkedStar()).toHaveAccessibleName(/5 von 5 Sternen/);
        expect(checkedStar()).toHaveAttribute('tabindex', '0');
        expect(
            screen
                .getAllByRole('radio')
                .filter((radio) => radio.getAttribute('tabindex') === '0'),
        ).toHaveLength(1);
        expect(screen.getByText('Hervorragend')).toBeInTheDocument();
    });

    it('selects the clicked star', async () => {
        render(StarRatingInput);

        await fireEvent.click(screen.getByRole('radio', { name: /^2 von 5/ }));

        expect(checkedStar()).toHaveAccessibleName(/2 von 5 Sternen/);
        expect(screen.getByText('Ausreichend')).toBeInTheDocument();
    });

    it('previews the hovered star without changing the value', async () => {
        render(StarRatingInput, { props: { value: 4 } });
        const firstStar = screen.getByRole('radio', { name: /^1 von 5/ });

        await fireEvent.mouseEnter(firstStar);
        expect(screen.getByText('Mangelhaft')).toBeInTheDocument();
        expect(checkedStar()).toHaveAccessibleName(/4 von 5 Sternen/);

        await fireEvent.mouseLeave(firstStar);
        expect(screen.getByText('Sehr gut')).toBeInTheDocument();
    });

    it('changes the value with arrow keys, Home and End within 1 to 5', async () => {
        render(StarRatingInput, { props: { value: 3 } });

        await fireEvent.keyDown(checkedStar(), { key: 'ArrowLeft' });
        expect(checkedStar()).toHaveAccessibleName(/^2 von 5/);
        expect(checkedStar()).toHaveFocus();

        await fireEvent.keyDown(checkedStar(), { key: 'Home' });
        await fireEvent.keyDown(checkedStar(), { key: 'ArrowDown' });
        expect(checkedStar()).toHaveAccessibleName(/^1 von 5/);

        await fireEvent.keyDown(checkedStar(), { key: 'End' });
        await fireEvent.keyDown(checkedStar(), { key: 'ArrowRight' });
        expect(checkedStar()).toHaveAccessibleName(/^5 von 5/);
    });
});
