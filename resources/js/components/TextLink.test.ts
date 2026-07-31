import { render, screen } from '@testing-library/svelte';
import { describe, expect, it } from 'vitest';
import TextLink from '@/components/TextLink.svelte';

describe('TextLink', () => {
    it('renders a link to the given href', () => {
        render(TextLink, { props: { href: '/login' } });

        const link = screen.getByRole('link') as HTMLAnchorElement;

        expect(new URL(link.href).pathname).toBe('/login');
    });
});
