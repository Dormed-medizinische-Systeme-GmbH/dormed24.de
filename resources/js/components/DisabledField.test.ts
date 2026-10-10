import { render, screen } from '@testing-library/svelte';
import { describe, expect, it } from 'vitest';
import DisabledField from '@/components/DisabledField.svelte';

describe('DisabledField', () => {
    it.each(['input', 'textarea', 'select', 'file'] as const)(
        'renders a disabled %s without a name, so it is never submitted',
        (kind) => {
            const { container } = render(DisabledField, {
                props: { label: 'Titelbild', kind, placeholder: 'Auswahl' },
            });

            const control = container.querySelector(
                'input, textarea, select',
            ) as HTMLElement;

            expect(control).toBeDisabled();
            expect(control).not.toHaveAttribute('name');
            expect(screen.getByLabelText('Titelbild')).toBe(control);
            expect(screen.getByText('Bald verfügbar')).toBeInTheDocument();
        },
    );

    it('shows the optional hint', () => {
        render(DisabledField, {
            props: { label: 'Autor', hint: 'Wird später ergänzt.' },
        });

        expect(screen.getByText('Wird später ergänzt.')).toBeInTheDocument();
    });
});
