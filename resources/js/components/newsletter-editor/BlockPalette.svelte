<script lang="ts" generics="T extends PaletteBlock">
    import Search from '@lucide/svelte/icons/search';
    import { Input } from '@/components/ui/input';
    import { blockIcon, blockLabel, groupBlocks } from './blocks';
    import type { PaletteBlock } from './blocks';

    let {
        blocks,
        onDragStart,
        onDragEnd,
    }: {
        blocks: T[];
        onDragStart: (block: T, event: DragEvent) => void;
        onDragEnd: () => void;
    } = $props();

    let query = $state('');

    const groups = $derived(groupBlocks(blocks, query));
</script>

<div class="flex flex-col gap-3">
    <div class="relative">
        <Search
            class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
            type="search"
            bind:value={query}
            placeholder="Block suchen"
            aria-label="Block suchen"
            class="pl-8"
        />
    </div>

    {#each groups as group (group.title)}
        <section class="flex flex-col gap-2">
            <h3
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                {group.title}
            </h3>
            <div role="list" class="grid grid-cols-2 gap-2">
                {#each group.blocks as block (block.id)}
                    {@const Icon = blockIcon(block)}
                    <div
                        role="listitem"
                        draggable="true"
                        title="In die Vorschau ziehen"
                        class="flex cursor-grab flex-col items-center gap-1.5 rounded-lg border bg-background px-2 py-3 text-xs font-medium transition-colors select-none hover:border-primary/40 hover:bg-accent active:cursor-grabbing"
                        ondragstart={(event) => onDragStart(block, event)}
                        ondragend={() => onDragEnd()}
                    >
                        <Icon class="size-5 text-muted-foreground" />
                        {blockLabel(block)}
                    </div>
                {/each}
            </div>
        </section>
    {:else}
        <p class="text-sm text-muted-foreground">
            Kein Block passt zu „{query}“.
        </p>
    {/each}
</div>
