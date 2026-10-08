export type ImageSize = { width: number; height: number };

/**
 * Natürliche Größe der Bilder, die im Canvas schon geladen sind,
 * je src-Attribut.
 */
export function naturalImageSizes(
    document: Document | null | undefined,
): Map<string, ImageSize> {
    const sizes = new Map<string, ImageSize>();

    for (const image of Array.from(document?.images ?? [])) {
        const src = image.getAttribute('src');

        if (src && image.naturalWidth > 0 && image.naturalHeight > 0) {
            sizes.set(src, {
                width: image.naturalWidth,
                height: image.naturalHeight,
            });
        }
    }

    return sizes;
}

function attribute(tag: string, name: string): string | null {
    const match = tag.match(new RegExp(`\\s${name}\\s*=\\s*"([^"]*)"`, 'i'));

    return match ? match[1] : null;
}

/**
 * MJML schreibt `<img width="430" height="auto">`. Outlook (Word-Engine)
 * ignoriert CSS und versteht `height="auto"` nicht: es nimmt die Höhe der
 * Bilddatei, staucht das Bild und lässt darunter Leerraum. Deshalb hier die
 * Höhe in Pixeln aus Breite und Seitenverhältnis eintragen. Andere Clients
 * richten sich weiter nach `height:auto` im Style.
 */
export function setOutlookImageHeights(
    html: string,
    sizes: Map<string, ImageSize>,
): string {
    return html.replace(/<img\b[^>]*>/gi, (tag) => {
        const src = attribute(tag, 'src')?.replaceAll('&amp;', '&');
        const width = Number(attribute(tag, 'width'));
        const size = src ? sizes.get(src) : undefined;

        if (!size || !width || attribute(tag, 'height') !== 'auto') {
            return tag;
        }

        const height = Math.round((width * size.height) / size.width);

        return tag.replace(/(\sheight\s*=\s*)"auto"/i, `$1"${height}"`);
    });
}
