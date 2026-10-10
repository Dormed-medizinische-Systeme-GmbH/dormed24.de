<script lang="ts">
    import type { Snippet } from 'svelte';

    let {
        id,
        title,
        description,
        children,
    }: {
        id: string;
        title: string;
        description?: string;
        children?: Snippet;
    } = $props();
</script>

<!-- Überschrift immer über dem Inhalt, nie daneben. -->
<section
    {id}
    class="flex scroll-mt-4 flex-col gap-4 border-b py-8 first:pt-2 last:border-b-0"
>
    <div>
        <h2 class="text-base font-semibold">{title}</h2>
        {#if description}
            <p class="text-sm text-muted-foreground">{description}</p>
        {/if}
    </div>
    <div class="flex flex-col gap-6">
        {@render children?.()}
    </div>
</section>
