/**
 * Layout-Varianten einer Admin-Seite: Dieselbe Route kann mehrere rein
 * optische Layouts anbieten. Die Auswahl steht in der URL (`?variant=B`),
 * damit sie teilbar und neuladestabil ist; die Standard-Variante bleibt
 * ohne Parameter.
 */
export type LayoutVariantOption<K extends string = string> = {
    readonly key: K;
    readonly label: string;
};

const PARAM = 'variant';
const BASE = 'http://localhost';

/** Liest die Variante aus einer URL; unbekannte oder fehlende Werte ergeben die erste Option. */
export function resolveLayoutVariant<K extends string>(
    url: string,
    options: readonly LayoutVariantOption<K>[],
): K {
    const value = new URL(url, BASE).searchParams.get(PARAM)?.toUpperCase();

    return (options.find((option) => option.key === value) ?? options[0]).key;
}

/**
 * Setzt die Variante in der URL (Pfad + Query + Hash). Die Standard-Variante
 * (erste Option) entfernt den Parameter wieder; andere Query-Parameter
 * bleiben erhalten.
 */
export function withLayoutVariant<K extends string>(
    url: string,
    key: K,
    options: readonly LayoutVariantOption<K>[],
): string {
    const target = new URL(url, BASE);

    if (key === options[0].key) {
        target.searchParams.delete(PARAM);
    } else {
        target.searchParams.set(PARAM, key);
    }

    return `${target.pathname}${target.search}${target.hash}`;
}
