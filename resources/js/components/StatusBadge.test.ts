import { render, screen } from '@testing-library/svelte';
import { describe, expect, it } from 'vitest';
import StatusBadge from '@/components/StatusBadge.svelte';
import type { StatusMap } from '@/lib/status';

const statuses: StatusMap = {
    draft: { label: 'Entwurf', tone: 'neutral' },
    published: { label: 'Veröffentlicht', tone: 'success' },
};

describe('StatusBadge', () => {
    it('shows the label and the colour of the tone', () => {
        render(StatusBadge, { props: { status: 'published', statuses } });

        const badge = screen.getByText('Veröffentlicht');
        expect(badge).toHaveClass('bg-emerald-100');
    });

    it('falls back to the raw value in neutral colour', () => {
        render(StatusBadge, { props: { status: 'archived', statuses } });

        expect(screen.getByText('archived')).toHaveClass('bg-gray-100');
    });
});
