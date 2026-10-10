/**
 * Bild-, Variationen- und Bewertungs-Aktionen laden nur die Props neu:
 * Ungespeicherte Stammdaten und der aktive Reiter bleiben erhalten.
 */
export const keepPageState = {
    preserveState: true,
    preserveScroll: true,
} as const;
