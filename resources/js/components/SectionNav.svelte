<script lang="ts">
    import { cn } from '@/lib/utils';

    export type SectionNavItem = { id: string; title: string; count?: string };

    /**
     * Sprungnavigation mit Scrollspy für Seiten aus `EditSection`-Abschnitten.
     * Der Inhalt scrollt im Layout-Container (`overflow-y-auto`), nicht im Fenster.
     */
    let { sections }: { sections: SectionNavItem[] } = $props();

    let nav = $state<HTMLElement>();
    let activeId = $state('');

    const current = $derived(activeId || sections[0]?.id);

    function goTo(id: string) {
        document
            .getElementById(id)
            ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    $effect(() => {
        const scroller = nav?.closest<HTMLElement>('.overflow-y-auto');

        if (!scroller) {
            return;
        }

        const update = () => {
            const top = scroller.getBoundingClientRect().top;
            let active = sections[0]?.id ?? '';

            for (const { id } of sections) {
                const el = document.getElementById(id);

                if (el && el.getBoundingClientRect().top - top < 120) {
                    active = id;
                }
            }

            activeId = active;
        };

        scroller.addEventListener('scroll', update, { passive: true });
        update();

        return () => scroller.removeEventListener('scroll', update);
    });
</script>

<nav
    bind:this={nav}
    aria-label="Abschnitte"
    class="sticky top-4 hidden flex-col gap-0.5 self-start lg:flex"
>
    {#each sections as section (section.id)}
        <button
            type="button"
            onclick={() => goTo(section.id)}
            aria-current={current === section.id ? 'location' : undefined}
            class={cn(
                'flex items-center justify-between rounded-md px-2.5 py-1.5 text-left text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground',
                current === section.id &&
                    'bg-accent font-medium text-foreground',
            )}
        >
            {section.title}
            {#if section.count}
                <span class="text-xs tabular-nums">{section.count}</span>
            {/if}
        </button>
    {/each}
</nav>
