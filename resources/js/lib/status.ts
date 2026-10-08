import { page } from '@inertiajs/svelte';

/**
 * Statusanzeige: Jeder Statuswert hat ein Label und einen Ton, der Ton
 * bestimmt die Farbe. Die Zuordnung Wert → Label/Ton kommt aus den
 * PHP-Enums (HasStatusBadge) als Prop, Bestellstatus als geteilte Prop
 * `orderStatuses`; die Farben gibt es nur hier.
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

/**
 * Bestellstatus aus der geteilten Prop `orderStatuses` (OrderStatus in PHP).
 */
export function orderStatusLabel(status: string): string {
    return statusLabel(page.props.orderStatuses ?? {}, status);
}

export function orderStatusBadgeClass(status: string): string {
    return statusClass(page.props.orderStatuses ?? {}, status);
}
