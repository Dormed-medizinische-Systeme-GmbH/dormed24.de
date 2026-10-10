<script lang="ts">
    import OrderStatusPill from '@/components/order-detail/OrderStatusPill.svelte';
    import {
        Table,
        TableBody,
        TableCell,
        TableHead,
        TableHeader,
        TableRow,
    } from '@/components/ui/table';
    import { formatPrice } from '@/lib/currency';
    import { formatDay } from '@/lib/customerDetail';
    import type { CustomerOrder } from '@/types';

    let { orders }: { orders: CustomerOrder[] } = $props();
</script>

{#if orders.length === 0}
    <p class="py-4 text-center text-sm text-muted-foreground">
        Keine Bestellungen vorhanden.
    </p>
{:else}
    <Table>
        <TableHeader>
            <TableRow>
                <TableHead>Nr.</TableHead>
                <TableHead>Datum</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Positionen</TableHead>
                <TableHead class="text-right">Gesamt</TableHead>
            </TableRow>
        </TableHeader>
        <TableBody>
            {#each orders as order (order.id)}
                <TableRow>
                    <TableCell class="font-medium">#{order.id}</TableCell>
                    <TableCell>{formatDay(order.created_at)}</TableCell>
                    <TableCell>
                        <OrderStatusPill status={order.status} size="sm" />
                    </TableCell>
                    <TableCell>{order.items.length}</TableCell>
                    <TableCell class="text-right font-semibold"
                        >{formatPrice(order.total_amount)}</TableCell
                    >
                </TableRow>
            {/each}
        </TableBody>
    </Table>
{/if}
