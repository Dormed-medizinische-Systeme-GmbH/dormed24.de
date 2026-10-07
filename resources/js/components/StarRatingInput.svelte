<script lang="ts">
    import Star from '@lucide/svelte/icons/star';
    import { cn } from '@/lib/utils';

    let {
        value = $bindable(5),
        id,
    }: {
        value?: number;
        id?: string;
    } = $props();

    const STARS = [1, 2, 3, 4, 5];
    const LABELS = [
        '',
        'Mangelhaft',
        'Ausreichend',
        'Gut',
        'Sehr gut',
        'Hervorragend',
    ];

    // Beim Überfahren zeigen die Sterne die Vorschau, sonst den gewählten Wert.
    let hovered = $state<number | null>(null);
    const shown = $derived(hovered ?? value);

    let buttons: HTMLButtonElement[] = [];

    /**
     * Tastatur wie bei einer Radiogruppe: Pfeiltasten wechseln den Wert,
     * Pos1/Ende springen an den Rand.
     */
    function onKeydown(event: KeyboardEvent) {
        const next = (
            {
                ArrowRight: value + 1,
                ArrowUp: value + 1,
                ArrowLeft: value - 1,
                ArrowDown: value - 1,
                Home: 1,
                End: 5,
            } as Record<string, number>
        )[event.key];

        if (next === undefined) {
            return;
        }

        event.preventDefault();
        value = Math.min(5, Math.max(1, next));
        buttons[value - 1]?.focus();
    }
</script>

<div class="grid gap-1">
    <div
        {id}
        role="radiogroup"
        aria-label="Sterne"
        class="flex items-center gap-1"
    >
        {#each STARS as star (star)}
            <button
                bind:this={buttons[star - 1]}
                type="button"
                role="radio"
                aria-checked={star === value}
                aria-label={`${star} von 5 Sternen – ${LABELS[star]}`}
                tabindex={star === value ? 0 : -1}
                class="rounded p-0.5 transition-transform hover:scale-110 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                onmouseenter={() => (hovered = star)}
                onmouseleave={() => (hovered = null)}
                onclick={() => (value = star)}
                onkeydown={onKeydown}
            >
                <Star
                    class={cn(
                        'size-8',
                        star <= shown
                            ? 'fill-[#f59e0b] text-[#f59e0b]'
                            : 'text-gray-300',
                    )}
                />
            </button>
        {/each}
    </div>
    <p class="text-sm text-muted-foreground">{LABELS[shown]}</p>
</div>
