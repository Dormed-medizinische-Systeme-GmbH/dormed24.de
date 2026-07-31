<!--
    Markup/CSS only - visibility and all consent logic are driven externally
    by resources/js/consent.js (vanilla-cookieconsent), which finds this
    element by id and wires up the data-consent-* hooks below. Hidden by
    default via CSS; consent.js toggles the --visible/--open classes.
-->
<div
    id="consent-banner"
    class="fixed inset-x-0 bottom-0 z-50 border-t bg-white px-4 py-2.5 shadow-[0_-10px_30px_rgba(15,23,42,0.12)]"
    role="dialog"
    aria-label="Cookie-Einstellungen"
>
    <div
        class="mx-auto flex max-w-7xl flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="max-w-3xl">
            <p class="mt-0.5 text-sm leading-5 text-muted-foreground">
                Wir verwenden Cookies, um den Shop bereitzustellen und später
                optionale Dienste einbinden zu können.
            </p>
        </div>

        <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
            <button
                type="button"
                class="rounded-lg border px-4 py-2 text-sm font-semibold text-brand-secondary transition hover:bg-muted"
                data-consent-toggle-preferences
            >
                Einstellungen
            </button>
            <button
                type="button"
                class="rounded-lg border px-4 py-2 text-sm font-semibold text-brand-secondary transition hover:bg-muted"
                data-consent-accept-necessary
            >
                Nur notwendige
            </button>
            <button
                type="button"
                class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary/90"
                data-consent-accept-all
            >
                Alle Cookies erlauben
            </button>
        </div>
    </div>

    <div
        class="consent-banner__preferences mx-auto mt-3 max-w-7xl border-t pt-3"
        data-consent-preferences
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center"
        >
            <label
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <input
                    type="checkbox"
                    checked
                    disabled
                    class="size-4 rounded border-border"
                />
                Notwendig (immer aktiv)
            </label>
            <label
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <input
                    type="checkbox"
                    data-consent-category="analytics"
                    class="size-4 rounded border-border"
                />
                Analyse
            </label>
            <label
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <input
                    type="checkbox"
                    data-consent-category="marketing"
                    class="size-4 rounded border-border"
                />
                Marketing
            </label>
            <button
                type="button"
                class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary/90 sm:ml-auto"
                data-consent-save-preferences
            >
                Auswahl speichern
            </button>
        </div>
    </div>
</div>

<style>
    /* --visible/--open are toggled by consent.js, not by this component -
       :global() keeps them out of Svelte's scoping (and "unused selector"
       warning), since it can't see them applied anywhere in the template. */
    #consent-banner {
        display: none;
    }

    #consent-banner:global(.consent-banner--visible) {
        display: block;
    }

    .consent-banner__preferences {
        display: none;
    }

    .consent-banner__preferences:global(.consent-banner__preferences--open) {
        display: block;
    }
</style>
