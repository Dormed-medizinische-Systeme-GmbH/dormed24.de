// Single source of truth for the German date format used across the app
// (DD.MM, zero-padded) — e.g. "07.07", not the browser-default "7.7".
export function formatDate(
    date: Date | string,
    options?: { withYear?: boolean },
): string {
    const d = typeof date === 'string' ? new Date(date) : date;

    return d.toLocaleDateString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        ...(options?.withYear ? { year: 'numeric' } : {}),
    });
}

/** Datum und Uhrzeit in Ortszeit, z. B. "10.10.2026, 09:30". */
export function formatDateTime(date: Date | string): string {
    const d = typeof date === 'string' ? new Date(date) : date;

    return d.toLocaleString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * ISO-Zeitpunkt (vom Server, UTC) als Wert für <input type="datetime-local">
 * in Ortszeit, z. B. "2026-10-10T09:30".
 */
export function toDateTimeLocal(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const d = new Date(iso);
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/** Wert aus <input type="datetime-local"> (Ortszeit) als ISO-Zeitpunkt in UTC. */
export function fromDateTimeLocal(value: string): string | null {
    return value ? new Date(value).toISOString() : null;
}
