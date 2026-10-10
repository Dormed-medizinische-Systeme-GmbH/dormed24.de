<script lang="ts">
    import { orderStatusBadgeClass, orderStatusLabel } from '@/lib/status';
    import { cn } from '@/lib/utils';

    let { status, size = 'md' }: { status: string; size?: 'sm' | 'md' | 'lg' } =
        $props();

    const sizeClasses = {
        sm: 'px-2.5 py-0.5 text-xs',
        md: 'px-3 py-1 text-sm',
        lg: 'px-4 py-1.5 text-base',
    } as const;
</script>

<span
    class={cn(
        'inline-flex items-center rounded-full font-medium',
        sizeClasses[size],
        orderStatusBadgeClass(status),
    )}
>
    {orderStatusLabel(status)}
</span>
