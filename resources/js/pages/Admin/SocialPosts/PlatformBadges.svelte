<script lang="ts">
    import { Badge } from '@/components/ui/badge';

    let {
        platforms,
        labels,
    }: { platforms: string[]; labels: Record<string, string> } = $props();
</script>

<div class="flex flex-wrap gap-1">
    {#each platforms as platform (platform)}
        <Badge variant="secondary">{labels[platform] ?? platform}</Badge>
    {/each}
</div>
