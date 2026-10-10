<script lang="ts">
    import { ChevronLeft, ChevronRight } from '@lucide/svelte';
    import { cn } from '@/lib/utils';

    type Option = { readonly key: string; readonly label: string };

    let {
        options,
        current,
        onselect,
    }: {
        options: readonly Option[];
        current: string;
        onselect: (key: string) => void;
    } = $props();

    const index = $derived(
        Math.max(
            0,
            options.findIndex((option) => option.key === current),
        ),
    );

    function step(direction: 1 | -1) {
        onselect(
            options[(index + direction + options.length) % options.length].key,
        );
    }

    const buttonClass =
        'inline-flex h-8 min-w-8 items-center justify-center rounded-full px-2 transition-colors hover:bg-background/20 focus-visible:ring-2 focus-visible:ring-background/60 focus-visible:outline-none';
</script>

<div
    role="group"
    aria-label="Layout-Variante der Bearbeitungsmaske"
    class="fixed bottom-4 left-1/2 z-50 flex max-w-[calc(100vw-1rem)] -translate-x-1/2 items-center gap-1 rounded-full bg-foreground p-1.5 text-sm text-background shadow-lg"
>
    <button
        type="button"
        class={buttonClass}
        aria-label="Vorherige Variante"
        onclick={() => step(-1)}
    >
        <ChevronLeft class="size-4" />
    </button>

    <span class="truncate px-2 whitespace-nowrap" aria-live="polite">
        <strong class="mr-1.5 font-semibold">{options[index].key}</strong>
        {options[index].label}
    </span>

    <button
        type="button"
        class={buttonClass}
        aria-label="Nächste Variante"
        onclick={() => step(1)}
    >
        <ChevronRight class="size-4" />
    </button>

    <div
        class="mx-1 hidden items-center gap-0.5 border-x border-background/30 px-1 sm:flex"
    >
        {#each options as option (option.key)}
            <button
                type="button"
                title={option.label}
                aria-label="Variante {option.key}: {option.label}"
                aria-current={option.key === current}
                class={cn(
                    buttonClass,
                    'size-7 min-w-7 px-0 font-semibold',
                    option.key === current &&
                        'bg-brand-accent text-white hover:bg-brand-accent',
                )}
                onclick={() => onselect(option.key)}
            >
                {option.key}
            </button>
        {/each}
    </div>
</div>
