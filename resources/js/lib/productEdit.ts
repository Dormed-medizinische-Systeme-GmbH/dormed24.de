/** Layout-Varianten der Produktbearbeitung (Admin), siehe lib/layoutVariants.ts. */
export const EDIT_VARIANTS = [
    { key: 'A', label: 'Reiter (Standard)' },
    { key: 'B', label: 'Zwei Spalten' },
    { key: 'C', label: 'Abschnitte' },
    { key: 'D', label: 'Bilder zuerst' },
    { key: 'E', label: 'Live-Vorschau' },
] as const;

/**
 * Bild-, Variationen- und Bewertungs-Aktionen laden nur die Props neu:
 * Ungespeicherte Stammdaten und der aktive Reiter bleiben erhalten.
 */
export const keepPageState = {
    preserveState: true,
    preserveScroll: true,
} as const;
