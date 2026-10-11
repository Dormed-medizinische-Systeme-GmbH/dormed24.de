import type { Snippet } from 'svelte';

/** Die Bausteine des Warenkorbs als Snippets; `Checkout/Index` besitzt Zustand und Aktionen. */
export type CartPageFields = {
    /** Leerer Warenkorb mit Weiter-einkaufen-Link. */
    empty: Snippet;
    /** `'rows'` = Positionen als Zeilen statt Tabelle. */
    items: Snippet<[('table' | 'rows')?]>;
    /** `'cards'` = Versandarten als wählbare Karten statt Liste. */
    shipping: Snippet<[('list' | 'cards')?]>;
    /** Zwischensumme, Versand, Gesamt, Netto und MwSt. */
    totals: Snippet;
    /** „Zur Kasse“ (deaktiviert bei leerem Warenkorb). */
    checkout: Snippet;
    /** Gesamtsumme als einzelne Zeile (für kompakte Leisten). */
    totalLine: Snippet;
    /** Preishinweis („* Preise inkl. MwSt. …“). */
    note: Snippet;
};

export type CartPageLayoutProps = {
    fields: CartPageFields;
    isEmpty: boolean;
};

/** Die Bausteine von „Bestellung abschließen“ als Snippets; `Checkout/Confirm` besitzt Zustand und Aktionen. */
export type CheckoutConfirmFields = {
    /** AGB-Hinweis mit Zustimmungs-Checkbox. */
    terms: Snippet;
    /** Lieferadresse inkl. „Adresse speichern“. */
    shippingAddress: Snippet;
    /** Rechnungsadresse (identisch mit Lieferadresse oder abweichend). */
    billingAddress: Snippet;
    /** Zwischensumme, Versand, Gesamt, Netto und MwSt. */
    totals: Snippet;
    /** PayPal-Buttons und Kauf auf Rechnung (oder Login-Aufforderung). `true` = Buttons nebeneinander. */
    pay: Snippet<[boolean?]>;
    /** Kurzfassung der Lieferadresse für eingeklappte Bereiche. */
    shippingSummary: Snippet;
    billingSummary: Snippet;
    /** Preishinweis („* Preise inkl. MwSt. …“). */
    note: Snippet;
};

export type CheckoutConfirmLayoutProps = {
    fields: CheckoutConfirmFields;
};
