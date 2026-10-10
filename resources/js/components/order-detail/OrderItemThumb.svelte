<script lang="ts">
    import Package from '@lucide/svelte/icons/package';
    import { cn } from '@/lib/utils';
    import type { OrderItem } from '@/types';

    let {
        item,
        class: className = 'size-10',
    }: { item: OrderItem; class?: string } = $props();
</script>

<div
    class={cn(
        'flex shrink-0 items-center justify-center overflow-hidden rounded bg-gray-100',
        className,
    )}
>
    {#if item.image_url}
        <img
            src={item.image_url}
            alt={item.product_name}
            class="size-full object-cover object-center"
        />
    {:else}
        <Package class="size-1/3 text-gray-300" />
    {/if}
</div>
