/** Layout-Varianten des Warenkorbs (/checkout), siehe lib/layoutVariants.ts. */
export const CART_VARIANTS = [
    { key: 'A', label: 'Tabelle + Seitenleiste (Standard)' },
    { key: 'B', label: 'Zeilen + feste Summe' },
    { key: 'C', label: 'Einspaltig' },
    { key: 'D', label: 'Versand zuerst' },
    { key: 'E', label: 'Kassenleiste unten' },
    { key: 'F', label: 'Beleg' },
] as const;
