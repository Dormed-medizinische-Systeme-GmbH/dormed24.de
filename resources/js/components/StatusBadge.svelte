<script lang="ts">
    import { Badge } from '@/components/ui/badge';
    import { statusClass, statusLabel } from '@/lib/status';
    import type { StatusMap } from '@/lib/status';

    let { status, statuses }: { status: string; statuses: StatusMap } =
        $props();
</script>

<Badge
    variant="outline"
    class="{statusClass(statuses, status)} border-transparent"
>
    {statusLabel(statuses, status)}
</Badge>
