import { render, screen } from '@testing-library/svelte';
import { describe, expect, it } from 'vitest';
import TableCellLink from '@/components/TableCellLink.svelte';

describe('TableCellLink', () => {
    it('renders the label as a link to the given href', () => {
        render(TableCellLink, {
            props: { href: '/admin/orders/5', label: 'Bestellung #5' },
        });

        const link = screen.getByRole('link', {
            name: 'Bestellung #5',
        }) as HTMLAnchorElement;

        expect(link).toBeInTheDocument();
        // Inertia's Link resolves href to an absolute URL against the
        // current origin - compare the pathname instead of the raw
        // attribute so this doesn't depend on jsdom's test origin.
        expect(new URL(link.href).pathname).toBe('/admin/orders/5');
    });
});
