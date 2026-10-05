import { router } from '@inertiajs/svelte';

export type ManufacturerFilterOption = {
    id: number;
    name: string;
    count: number;
};

/** Fachrichtungen: im Datenmodell noch nicht vorhanden, Backend liefert leere Listen. */
export type SpecialtyFilterOption = { id: string; name: string; count: number };

export type ProductListFilters = {
    manufacturers: number[];
    specialties: string[];
};

export type ProductListFilterOptions = {
    manufacturers: ManufacturerFilterOption[];
    specialties: SpecialtyFilterOption[];
    /** Anzahl der Produkte im aktuellen Listen-Scope ohne Hersteller-Filter. */
    total: number;
};

const MANUFACTURER_PARAM = 'hersteller[]';

/**
 * Lädt die aktuelle Liste (Suche/Kategorie bleiben erhalten) mit der neuen
 * Hersteller-Auswahl neu. `page` fliegt raus, damit Infinite Scroll wieder
 * bei Seite 1 beginnt.
 */
export function visitWithManufacturerFilter(manufacturerIds: number[]): void {
    const url = new URL(window.location.href);

    url.searchParams.delete(MANUFACTURER_PARAM);
    url.searchParams.delete('hersteller');
    url.searchParams.delete('page');

    for (const id of manufacturerIds) {
        url.searchParams.append(MANUFACTURER_PARAM, String(id));
    }

    router.visit(url.toString(), { preserveScroll: false });
}

/**
 * Trefferzahl für eine (noch nicht angewendete) Hersteller-Auswahl. Jedes
 * Produkt hat höchstens einen Hersteller, die Summe der Einzelzahlen ist
 * also exakt.
 */
export function countForManufacturers(
    options: ProductListFilterOptions,
    manufacturerIds: number[],
): number {
    if (manufacturerIds.length === 0) {
        return options.total;
    }

    return options.manufacturers
        .filter((option) => manufacturerIds.includes(option.id))
        .reduce((sum, option) => sum + option.count, 0);
}
