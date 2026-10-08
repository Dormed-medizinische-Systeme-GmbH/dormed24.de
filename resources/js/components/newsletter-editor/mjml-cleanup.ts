/** Fehlerobjekt von mjml-browser (nur die Felder, die hier gebraucht werden). */
export type MjmlError = {
    tagName?: string;
    message?: string;
    formattedMessage?: string;
};

const ILLEGAL_ATTRIBUTES = /^Attributes? (.+?) (?:is|are) illegal$/;

/**
 * Illegale Attribute je Tag aus den MJML-Hinweisen, z. B.
 * "Attributes color, border are illegal" an mj-hero.
 */
export function illegalAttributes(errors: MjmlError[]): Map<string, string[]> {
    const result = new Map<string, string[]>();

    for (const error of errors) {
        const match = error.message?.trim().match(ILLEGAL_ATTRIBUTES);

        if (!error.tagName || !match) {
            continue;
        }

        const names = match[1].split(',').map((name) => name.trim());
        result.set(error.tagName, [
            ...new Set([...(result.get(error.tagName) ?? []), ...names]),
        ]);
    }

    return result;
}

/**
 * Entfernt die gemeldeten Attribute an allen Tags dieses Typs. Ein Attribut,
 * das MJML für einen Tag nicht kennt, ist an jeder Stelle illegal; MJML
 * ignoriert es ohnehin, es erzeugt nur Hinweise.
 */
export function removeIllegalAttributes(
    mjml: string,
    illegal: Map<string, string[]>,
): string {
    let cleaned = mjml;

    for (const [tagName, names] of illegal) {
        const tag = new RegExp(`<${tagName}(?=[\\s/>])[^>]*>`, 'g');

        cleaned = cleaned.replace(tag, (opening) =>
            names.reduce(
                (current, name) =>
                    current.replace(
                        new RegExp(`\\s${name}\\s*=\\s*"[^"]*"`, 'g'),
                        '',
                    ),
                opening,
            ),
        );
    }

    return cleaned;
}
