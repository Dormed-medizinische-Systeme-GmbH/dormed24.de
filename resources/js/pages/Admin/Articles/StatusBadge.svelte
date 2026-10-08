<script lang="ts">
    import { Badge } from '@/components/ui/badge';

    let { status }: { status: string } = $props();

    const labels: Record<string, string> = {
        draft: 'Entwurf',
        scheduled: 'Geplant',
        published: 'Veröffentlicht',
    };
    const classes: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-700',
        scheduled: 'bg-blue-100 text-blue-800',
        published: 'bg-emerald-100 text-emerald-800',
    };
</script>

<Badge
    variant="outline"
    class="{classes[status] ?? 'bg-gray-100 text-gray-700'} border-transparent"
>
    {labels[status] ?? status}
</Badge>
