import { fireEvent, render, screen, within } from '@testing-library/svelte';
import { describe, expect, it } from 'vitest';
import DashboardRangeControl from '@/components/dashboard/DashboardRangeControl.svelte';

const presets = [
    { value: '7d', label: 'Letzte 7 Tage' },
    { value: '30d', label: 'Letzte 30 Tage' },
    { value: '365d', label: 'Letztes Jahr' },
];

function setup(value = '7d') {
    return render(DashboardRangeControl, { props: { presets, value } });
}

describe('DashboardRangeControl', () => {
    it('shows all ranges as segments from 1000px, hidden below', () => {
        setup('30d');

        const group = screen.getByRole('group', { name: 'Zeitraum auswählen' });

        // Tailwind: unter 1000 px ausgeblendet, ab 1000 px sichtbar
        expect(group.className).toContain('hidden');
        expect(group.className).toContain('min-[1000px]:inline-flex');
        expect(within(group).getAllByRole('button')).toHaveLength(3);
        expect(
            within(group).getByRole('button', { name: 'Letzte 30 Tage' }),
        ).toHaveAttribute('aria-pressed', 'true');
        expect(
            within(group).getByRole('button', { name: 'Letzte 7 Tage' }),
        ).toHaveAttribute('aria-pressed', 'false');
    });

    it('switches the range via a segment', async () => {
        setup('7d');
        const group = screen.getByRole('group', { name: 'Zeitraum auswählen' });

        await fireEvent.click(
            within(group).getByRole('button', { name: 'Letztes Jahr' }),
        );

        expect(
            within(group).getByRole('button', { name: 'Letztes Jahr' }),
        ).toHaveAttribute('aria-pressed', 'true');
    });

    it('offers the dropdown below 1000px with the current range as label', () => {
        const { container } = setup('365d');

        const wrapper = container.querySelector('.min-\\[1000px\\]\\:hidden');
        const trigger = screen.getByRole('button', {
            name: 'Zeitraum auswählen',
            hidden: true,
        });

        expect(wrapper).not.toBeNull();
        expect(wrapper).toContainElement(trigger);
        expect(trigger).toHaveTextContent('Letztes Jahr');
    });

    it('switches the range via the dropdown', async () => {
        setup('7d');
        const trigger = screen
            .getAllByRole('button', { name: 'Zeitraum auswählen' })
            .find((el) => el.tagName === 'BUTTON') as HTMLElement;

        await fireEvent.click(trigger);
        await fireEvent.click(
            await screen.findByRole('button', { name: /Letzte 30 Tage$/ }),
        );

        expect(trigger).toHaveTextContent('Letzte 30 Tage');
    });
});
