import { beforeEach, describe, expect, it, vi } from 'vitest';

const cookieConsentMock = {
    run: vi.fn().mockResolvedValue(undefined),
    acceptCategory: vi.fn(),
    validConsent: vi.fn().mockReturnValue(false),
};

vi.mock('vanilla-cookieconsent', () => cookieConsentMock);

// Dynamic import so the module's top-level CookieConsent.run() call runs
// after the mock above is in place, and after this file's own const
// declarations (see the vi.hoisted note in PayPalButton.test.ts for why a
// static import would break here).
const { initBanner } = await import('@/consent.js');

function renderBannerMarkup(): void {
    document.body.innerHTML = `
        <div id="consent-banner">
            <button data-consent-accept-all>Alle Cookies erlauben</button>
            <button data-consent-accept-necessary>Nur notwendige</button>
            <button data-consent-toggle-preferences>Einstellungen</button>
            <div data-consent-preferences>
                <input type="checkbox" data-consent-category="analytics" />
                <input type="checkbox" data-consent-category="marketing" />
                <button data-consent-save-preferences>Auswahl speichern</button>
            </div>
        </div>
    `;
}

function click(selector: string): void {
    (document.querySelector(selector) as HTMLElement).click();
}

function check(selector: string, checked: boolean): void {
    (document.querySelector(selector) as HTMLInputElement).checked = checked;
}

describe('initBanner', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
        cookieConsentMock.acceptCategory.mockClear();
        cookieConsentMock.validConsent.mockReset().mockReturnValue(false);
    });

    it('does nothing when the banner element is not in the DOM', () => {
        expect(() => initBanner()).not.toThrow();
    });

    it('shows the banner when there is no valid consent yet', () => {
        renderBannerMarkup();

        initBanner();

        expect(document.getElementById('consent-banner')).toHaveClass(
            'consent-banner--visible',
        );
    });

    it('leaves the banner hidden when consent is already valid', () => {
        renderBannerMarkup();
        cookieConsentMock.validConsent.mockReturnValue(true);

        initBanner();

        expect(document.getElementById('consent-banner')).not.toHaveClass(
            'consent-banner--visible',
        );
    });

    it('accepts all categories and hides the banner on "accept all"', () => {
        renderBannerMarkup();
        initBanner();

        click('[data-consent-accept-all]');

        expect(cookieConsentMock.acceptCategory).toHaveBeenCalledWith('all');
        expect(document.getElementById('consent-banner')).not.toHaveClass(
            'consent-banner--visible',
        );
    });

    it('accepts no optional categories and hides the banner on "accept necessary"', () => {
        renderBannerMarkup();
        initBanner();

        click('[data-consent-accept-necessary]');

        expect(cookieConsentMock.acceptCategory).toHaveBeenCalledWith([]);
        expect(document.getElementById('consent-banner')).not.toHaveClass(
            'consent-banner--visible',
        );
    });

    it('toggles the preferences panel open and closed', () => {
        renderBannerMarkup();
        initBanner();
        const panel = document.querySelector(
            '[data-consent-preferences]',
        ) as HTMLElement;

        click('[data-consent-toggle-preferences]');
        expect(panel).toHaveClass('consent-banner__preferences--open');

        click('[data-consent-toggle-preferences]');
        expect(panel).not.toHaveClass('consent-banner__preferences--open');
    });

    it('saves only the checked categories on "save preferences"', () => {
        renderBannerMarkup();
        initBanner();

        check('[data-consent-category="analytics"]', true);
        check('[data-consent-category="marketing"]', false);
        click('[data-consent-save-preferences]');

        expect(cookieConsentMock.acceptCategory).toHaveBeenCalledWith([
            'analytics',
        ]);
        expect(document.getElementById('consent-banner')).not.toHaveClass(
            'consent-banner--visible',
        );
    });

    it('saves both categories when both are checked', () => {
        renderBannerMarkup();
        initBanner();

        check('[data-consent-category="analytics"]', true);
        check('[data-consent-category="marketing"]', true);
        click('[data-consent-save-preferences]');

        expect(cookieConsentMock.acceptCategory).toHaveBeenCalledWith([
            'analytics',
            'marketing',
        ]);
    });
});
