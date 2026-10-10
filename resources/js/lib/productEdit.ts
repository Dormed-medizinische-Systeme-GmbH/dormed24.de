/**
 * Layout-Varianten der Produktbearbeitung (Admin). Die Auswahl steht in der
 * URL (`?variant=B`), damit sie teilbar und neuladestabil ist; die Standard-
 * Variante A bleibt ohne Parameter.
 */
export const EDIT_VARIANTS = [
    { key: 'A', label: 'Reiter (Standard)' },
    { key: 'B', label: 'Zwei Spalten' },
    { key: 'C', label: 'Abschnitte' },
    { key: 'D', label: 'Bilder zuerst' },
    { key: 'E', label: 'Live-Vorschau' },
] as const;

export type EditVariantKey = (typeof EDIT_VARIANTS)[number]['key'];

export const DEFAULT_EDIT_VARIANT: EditVariantKey = 'A';

const PARAM = 'variant';
const BASE = 'http://localhost';

/** Liest die Variante aus einer URL; unbekannte oder fehlende Werte ergeben A. */
export function resolveEditVariant(url: string): EditVariantKey {
    const value = new URL(url, BASE).searchParams.get(PARAM)?.toUpperCase();

    return (
        EDIT_VARIANTS.find((variant) => variant.key === value)?.key ??
        DEFAULT_EDIT_VARIANT
    );
}

/**
 * Setzt die Variante in der URL (Pfad + Query + Hash). Die Standard-Variante
 * entfernt den Parameter wieder; andere Query-Parameter bleiben erhalten.
 */
export function withEditVariant(url: string, key: EditVariantKey): string {
    const target = new URL(url, BASE);

    if (key === DEFAULT_EDIT_VARIANT) {
        target.searchParams.delete(PARAM);
    } else {
        target.searchParams.set(PARAM, key);
    }

    return `${target.pathname}${target.search}${target.hash}`;
}

/**
 * Bild-, Variationen- und Bewertungs-Aktionen laden nur die Props neu:
 * Ungespeicherte Stammdaten und der aktive Reiter bleiben erhalten.
 */
export const keepPageState = {
    preserveState: true,
    preserveScroll: true,
} as const;
