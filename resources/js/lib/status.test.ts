import { describe, expect, it, vi } from 'vitest';
import {
    orderStatusBadgeClass,
    orderStatusLabel,
    statusClass,
    statusLabel,
} from '@/lib/status';
import type { StatusMap } from '@/lib/status';

vi.mock('@inertiajs/svelte', () => ({
    page: {
        props: {
            orderStatuses: {
                paid: { label: 'Bezahlt', tone: 'success' },
                pending: { label: 'Ausstehend', tone: 'warning' },
            },
        },
    },
}));

const statuses: StatusMap = {
    draft: { label: 'Entwurf', tone: 'neutral' },
    failed: { label: 'Fehlgeschlagen', tone: 'danger' },
};

describe('statusLabel / statusClass', () => {
    it('reads label and tone colour from the map', () => {
        expect(statusLabel(statuses, 'failed')).toBe('Fehlgeschlagen');
        expect(statusClass(statuses, 'failed')).toBe('bg-red-100 text-red-800');
    });

    it('falls back to the raw value in neutral colour', () => {
        expect(statusLabel(statuses, 'archived')).toBe('archived');
        expect(statusClass(statuses, 'archived')).toBe(
            'bg-gray-100 text-gray-700',
        );
    });
});

describe('order statuses', () => {
    it('come from the shared orderStatuses prop', () => {
        expect(orderStatusLabel('paid')).toBe('Bezahlt');
        expect(orderStatusBadgeClass('pending')).toBe(
            'bg-yellow-100 text-yellow-700',
        );
        expect(orderStatusLabel('processing')).toBe('processing');
    });
});
