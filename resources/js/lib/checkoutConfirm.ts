/** Layout-Varianten der Bestellung abschließen (/checkout/confirm), siehe lib/layoutVariants.ts. */
export const CHECKOUT_CONFIRM_VARIANTS = [
    { key: 'A', label: 'Adressen + Seitenleiste (Standard)' },
    { key: 'B', label: 'Einspaltig' },
    { key: 'C', label: 'Reiter' },
    { key: 'D', label: 'Schritte' },
    { key: 'E', label: 'Zahlungsleiste unten' },
    { key: 'F', label: 'Adressen einklappbar' },
] as const;
