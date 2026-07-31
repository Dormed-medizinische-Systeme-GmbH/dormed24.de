import { render, screen } from '@testing-library/svelte';
import { describe, expect, it } from 'vitest';
import CookieBanner from '@/components/CookieBanner.svelte';

// CookieBanner.svelte is markup/CSS only - all consent logic and visibility
// toggling is wired up externally by resources/js/consent.js, see
// consent.test.ts. These tests only verify the hooks it depends on exist.

describe('CookieBanner', () => {
    it('renders the dialog with the id and aria-label consent.js looks for', () => {
        render(CookieBanner);

        const banner = document.getElementById('consent-banner');

        expect(banner).toBeInTheDocument();
        expect(banner).toHaveAttribute('role', 'dialog');
        expect(banner).toHaveAttribute('aria-label', 'Cookie-Einstellungen');
    });

    it('is hidden by default until consent.js adds the --visible class', () => {
        render(CookieBanner);

        const banner = document.getElementById('consent-banner') as HTMLElement;

        expect(getComputedStyle(banner).display).toBe('none');

        banner.classList.add('consent-banner--visible');

        expect(getComputedStyle(banner).display).toBe('block');
    });

    it('exposes the three main action buttons as data hooks', () => {
        render(CookieBanner);

        expect(
            document.querySelector('[data-consent-accept-all]'),
        ).toBeInTheDocument();
        expect(
            document.querySelector('[data-consent-accept-necessary]'),
        ).toBeInTheDocument();
        expect(
            document.querySelector('[data-consent-toggle-preferences]'),
        ).toBeInTheDocument();
    });

    it('renders a preferences panel, hidden until toggled open', () => {
        render(CookieBanner);

        const panel = document.querySelector(
            '[data-consent-preferences]',
        ) as HTMLElement;

        expect(panel).toBeInTheDocument();
        expect(getComputedStyle(panel).display).toBe('none');

        panel.classList.add('consent-banner__preferences--open');

        expect(getComputedStyle(panel).display).toBe('block');
    });

    it('exposes analytics/marketing category checkboxes and a save button', () => {
        render(CookieBanner);

        expect(
            document.querySelector('[data-consent-category="analytics"]'),
        ).toBeInTheDocument();
        expect(
            document.querySelector('[data-consent-category="marketing"]'),
        ).toBeInTheDocument();
        expect(
            document.querySelector('[data-consent-save-preferences]'),
        ).toBeInTheDocument();
    });

    it('renders the necessary-cookies checkbox as always checked and disabled', () => {
        render(CookieBanner);

        // hidden: true - the preferences panel is display:none until
        // toggled open, which correctly excludes it from the a11y tree by
        // default; we only care about its attributes here, not visibility.
        const necessary = screen.getByRole('checkbox', {
            name: /Notwendig/,
            hidden: true,
        });

        expect(necessary).toBeChecked();
        expect(necessary).toBeDisabled();
    });
});
