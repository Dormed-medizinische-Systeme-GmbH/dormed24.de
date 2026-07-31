import { fireEvent, render, screen } from '@testing-library/svelte';
import { beforeEach, describe, expect, it } from 'vitest';
import CookieBanner from '@/components/CookieBanner.svelte';

describe('CookieBanner', () => {
    beforeEach(() => {
        sessionStorage.clear();
    });

    it('shows the banner when it has not been dismissed before', () => {
        render(CookieBanner);

        expect(screen.getByText(/Wir verwenden Cookies/)).toBeInTheDocument();
    });

    it('stays hidden when it was already dismissed in this session', () => {
        sessionStorage.setItem('cookie-banner-dismissed', '1');

        render(CookieBanner);

        expect(
            screen.queryByText(/Wir verwenden Cookies/),
        ).not.toBeInTheDocument();
    });

    it('hides the banner and remembers the dismissal when "Nur notwendige" is clicked', async () => {
        render(CookieBanner);

        await fireEvent.click(
            screen.getByRole('button', { name: 'Nur notwendige' }),
        );

        expect(
            screen.queryByText(/Wir verwenden Cookies/),
        ).not.toBeInTheDocument();
        expect(sessionStorage.getItem('cookie-banner-dismissed')).toBe('1');
    });

    it('hides the banner when "Alle Cookies erlauben" is clicked', async () => {
        render(CookieBanner);

        await fireEvent.click(
            screen.getByRole('button', { name: 'Alle Cookies erlauben' }),
        );

        expect(
            screen.queryByText(/Wir verwenden Cookies/),
        ).not.toBeInTheDocument();
        expect(sessionStorage.getItem('cookie-banner-dismissed')).toBe('1');
    });
});
