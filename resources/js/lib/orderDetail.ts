import type { AddressSnapshot } from '@/types';

export const paymentStatusLabels: Record<string, string> = {
    CREATED: 'Erstellt',
    APPROVED: 'Bestätigt',
    COMPLETED: 'Abgeschlossen',
    PENDING: 'In Prüfung bei PayPal',
    MISMATCH: 'Betrag abweichend (erstatten)',
    FAILED: 'Fehlgeschlagen',
    REFUNDED: 'Rückerstattet',
    REVERSED: 'Von PayPal zurückgebucht',
};

export function paymentStatusLabel(status: string): string {
    return paymentStatusLabels[status] ?? status;
}

/** Bestellzeitpunkt, z. B. „12. September 2026, 14:30“. */
export function formatOrderedAt(iso: string): string {
    return new Date(iso).toLocaleDateString('de-DE', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function formatAddress(a: AddressSnapshot): string {
    if (!a) {
        return '—';
    }

    const lines = [
        a.company,
        (a.salutation ? a.salutation + ' ' : '') +
            (a.first_name ?? '') +
            ' ' +
            (a.last_name ?? ''),
        (a.street ?? '') + ' ' + (a.house_number ?? ''),
        a.address_line2,
        (a.zip ?? '') + ' ' + (a.city ?? ''),
    ].filter(Boolean);

    return lines.join(', ') || '—';
}

/** Layout-Varianten der Bestelldetail-Ansicht, siehe lib/layoutVariants.ts. */
export const ORDER_VARIANTS = [
    { key: 'A', label: 'Karten (Standard)' },
    { key: 'B', label: 'Beleg' },
    { key: 'C', label: 'Seitenleiste' },
    { key: 'D', label: 'Kennzahlen + Reiter' },
    { key: 'E', label: 'Zahlung im Fokus' },
] as const;
