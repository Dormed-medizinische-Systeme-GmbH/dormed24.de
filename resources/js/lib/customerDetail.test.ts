import { describe, expect, it } from 'vitest';
import { customerStats, customerTimeline } from '@/lib/customerDetail';
import type { CustomerDetail } from '@/types';

const customer = (overrides: Partial<CustomerDetail> = {}): CustomerDetail => ({
    id: 1,
    name: 'Erika Muster',
    email: 'erika@example.test',
    email_verified_at: '2026-01-02T10:00:00Z',
    created_at: '2026-01-01T10:00:00Z',
    addresses: [],
    orders: [
        {
            id: 10,
            status: 'paid',
            total_amount: '100.00',
            created_at: '2026-03-01T10:00:00Z',
            items: [],
        },
        {
            id: 11,
            status: 'pending',
            total_amount: '50.50',
            created_at: '2026-05-01T10:00:00Z',
            items: [],
        },
    ],
    ...overrides,
});

describe('customer detail helpers', () => {
    it('derives order statistics from the loaded orders', () => {
        const stats = customerStats(customer());

        expect(stats.orderCount).toBe(2);
        expect(stats.ordersTotal).toBeCloseTo(150.5);
        expect(stats.averageOrderValue).toBeCloseTo(75.25);
        expect(stats.lastOrderAt).toBe('2026-05-01T10:00:00Z');
    });

    it('has no average and no last order without orders', () => {
        const stats = customerStats(customer({ orders: [] }));

        expect(stats.orderCount).toBe(0);
        expect(stats.ordersTotal).toBe(0);
        expect(stats.averageOrderValue).toBeNull();
        expect(stats.lastOrderAt).toBeNull();
    });

    it('builds the timeline newest first with registration and verification', () => {
        expect(customerTimeline(customer()).map((event) => event.key)).toEqual([
            'order-11',
            'order-10',
            'verified',
            'registered',
        ]);
    });

    it('omits the verification event for unverified customers', () => {
        const keys = customerTimeline(
            customer({ email_verified_at: null }),
        ).map((event) => event.key);

        expect(keys).not.toContain('verified');
        expect(keys.at(-1)).toBe('registered');
    });
});
