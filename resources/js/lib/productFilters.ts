import { router } from '@inertiajs/svelte';

export type ManufacturerFilterOption = {
    id: number;
    name: string;
    count: number;
};

export type SpecialtyFilterOption = { id: string; name: string; count: number };

export type ProductListFilters = {
    manufacturers: number[];
    specialties: string[];
};

export type ProductListFilterOptions = {
    /** Anzahl je Hersteller unter Berücksichtigung des aktiven Fachrichtungs-Filters. */
    manufacturers: ManufacturerFilterOption[];
    /** Anzahl je Fachrichtung unter Berücksichtigung des aktiven Hersteller-Filters. */
    specialties: SpecialtyFilterOption[];
    /** Anzahl der Produkte im aktuellen Listen-Scope ohne jeden Filter. */
    total: number;
    /** Anzahl mit aktivem Fachrichtungs-, aber ohne Hersteller-Filter. */
    totalWithSpecialtyFilter: number;
};

const MANUFACTURER_PARAM = 'hersteller[]';
const SPECIALTY_PARAM = 'fachrichtung[]';

/**
 * Lädt die aktuelle Liste (Suche/Kategorie bleiben erhalten) mit der neuen
 * Filterauswahl neu. `page` fliegt raus, damit Infinite Scroll wieder bei
 * Seite 1 beginnt.
 */
export function visitWithFilters(filters: ProductListFilters): void {
    const url = new URL(window.location.href);

    for (const param of [
        MANUFACTURER_PARAM,
        'hersteller',
        SPECIALTY_PARAM,
        'fachrichtung',
        'page',
    ]) {
        url.searchParams.delete(param);
    }

    for (const id of filters.manufacturers) {
        url.searchParams.append(MANUFACTURER_PARAM, String(id));
    }

    for (const slug of filters.specialties) {
        url.searchParams.append(SPECIALTY_PARAM, slug);
    }

    router.visit(url.toString(), { preserveScroll: false });
}

export function clearFilters(): void {
    visitWithFilters({ manufacturers: [], specialties: [] });
}

function sameSelection<T>(a: T[], b: T[]): boolean {
    return a.length === b.length && a.every((value) => b.includes(value));
}

/**
 * Trefferzahl für eine (noch nicht angewendete) Auswahl, oder null, wenn
 * sie sich clientseitig nicht exakt bestimmen lässt.
 *
 * Exakt geht es nur, solange die Fachrichtungen der angewendeten Auswahl
 * entsprechen: Dann zählen die Hersteller-Zahlen bereits mit diesem Filter,
 * und weil jedes Produkt höchstens einen Hersteller hat, ist die Summe exakt.
 * Fachrichtungen überschneiden sich (ein Produkt hat mehrere), ihre Zahlen
 * lassen sich also nicht addieren – außer es ist genau eine gewählt und die
 * Hersteller sind unverändert.
 */
export function countForSelection(
    options: ProductListFilterOptions,
    applied: ProductListFilters,
    pending: ProductListFilters,
): number | null {
    if (!sameSelection(pending.specialties, applied.specialties)) {
        if (
            pending.specialties.length === 1 &&
            sameSelection(pending.manufacturers, applied.manufacturers)
        ) {
            return (
                options.specialties.find(
                    (option) => option.id === pending.specialties[0],
                )?.count ?? 0
            );
        }

        return null;
    }

    if (pending.manufacturers.length === 0) {
        return options.totalWithSpecialtyFilter;
    }

    return options.manufacturers
        .filter((option) => pending.manufacturers.includes(option.id))
        .reduce((sum, option) => sum + option.count, 0);
}
