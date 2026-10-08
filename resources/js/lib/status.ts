/**
 * Statusanzeige im Admin: Jeder Statuswert hat ein Label und einen Ton,
 * der Ton bestimmt die Farbe. Die Zuordnung Wert → Label/Ton liefert die
 * jeweilige Seite; die Farben gibt es nur hier.
 */
export type StatusTone = 'neutral' | 'info' | 'success' | 'warning' | 'danger';

export type StatusMap = Record<string, { label: string; tone: StatusTone }>;

const TONE_CLASSES: Record<StatusTone, string> = {
    neutral: 'bg-gray-100 text-gray-700',
    info: 'bg-blue-100 text-blue-800',
    success: 'bg-emerald-100 text-emerald-800',
    warning: 'bg-yellow-100 text-yellow-700',
    danger: 'bg-red-100 text-red-800',
};

export function statusLabel(statuses: StatusMap, status: string): string {
    return statuses[status]?.label ?? status;
}

export function statusClass(statuses: StatusMap, status: string): string {
    return TONE_CLASSES[statuses[status]?.tone ?? 'neutral'];
}

export const ARTICLE_STATUSES: StatusMap = {
    draft: { label: 'Entwurf', tone: 'neutral' },
    scheduled: { label: 'Geplant', tone: 'info' },
    published: { label: 'Veröffentlicht', tone: 'success' },
};

export const SOCIAL_POST_STATUSES: StatusMap = {
    ...ARTICLE_STATUSES,
    failed: { label: 'Fehlgeschlagen', tone: 'danger' },
};
