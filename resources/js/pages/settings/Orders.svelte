<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import * as Table from '@/components/ui/table';
    import { formatPrice } from '@/lib/currency';
    import { orderStatusBadgeClass, orderStatusLabel } from '@/lib/orderStatus';

    type Order = {
        id: number;
        status: string;
        total_amount: string;
        created_at: string;
    };

    let { orders }: { orders: Order[] } = $props();
</script>

<AppHead title="Bestellungen" />

<div class="space-y-6">
    <Heading
        title="Meine Bestellungen"
        description="Übersicht Ihrer bisherigen Bestellungen"
    />

    {#if orders.length === 0}
        <p class="py-8 text-center text-sm text-muted-foreground">
            Noch keine Bestellungen vorhanden.
        </p>
    {:else}
        <Table.Root>
            <Table.Header>
                <Table.Row>
                    <Table.Head>Nr.</Table.Head>
                    <Table.Head>Datum</Table.Head>
                    <Table.Head>Status</Table.Head>
                    <Table.Head class="text-right">Gesamt</Table.Head>
                </Table.Row>
            </Table.Header>
            <Table.Body>
                {#each orders as order (order.id)}
                    <Table.Row class="hover:bg-muted/40">
                        <Table.Cell class="font-medium">
                            <Link
                                href={`/customer/orders/${order.id}`}
                                class="block w-full text-primary hover:underline"
                            >
                                #{order.id}
                            </Link>
                        </Table.Cell>
                        <Table.Cell>
                            <Link
                                href={`/customer/orders/${order.id}`}
                                class="block w-full"
                            >
                                {new Date(order.created_at).toLocaleDateString(
                                    'de-DE',
                                )}
                            </Link>
                        </Table.Cell>
                        <Table.Cell>
                            <Link
                                href={`/customer/orders/${order.id}`}
                                class="block w-full"
                            >
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {orderStatusBadgeClass(
                                        order.status,
                                    )}"
                                >
                                    {orderStatusLabel(order.status)}
                                </span>
                            </Link>
                        </Table.Cell>
                        <Table.Cell class="text-right font-semibold">
                            <Link
                                href={`/customer/orders/${order.id}`}
                                class="block w-full"
                            >
                                {formatPrice(order.total_amount)}
                            </Link>
                        </Table.Cell>
                    </Table.Row>
                {/each}
            </Table.Body>
        </Table.Root>
    {/if}
</div>
