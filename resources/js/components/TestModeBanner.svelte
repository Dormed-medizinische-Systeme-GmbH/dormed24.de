<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import { FlaskConical } from '@lucide/svelte';
</script>

{#if page.props.sandbox}
    <div
        class="flex items-center justify-center gap-3 bg-amber-400 px-4 py-1.5 text-sm font-medium text-amber-950"
    >
        <FlaskConical class="size-4 shrink-0" />
        <span>Sandbox-Umgebung — keine echten Zahlungen möglich</span>
    </div>
{/if}
