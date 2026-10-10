<script lang="ts" generics="T extends string">
    import { Button } from '@/components/ui/button';
    import * as Popover from '@/components/ui/popover';

    /**
     * Zeitraum-Auswahl für den App-Header: Ab 1000 px Breite alle Zeiträume
     * als Segmente in einer Zeile, darunter als Dropdown.
     */
    let {
        presets,
        value = $bindable(),
    }: { presets: { value: T; label: string }[]; value: T } = $props();

    let open = $state(false);

    const label = $derived(
        presets.find((preset) => preset.value === value)?.label ?? 'Zeitraum',
    );
</script>

<div
    role="group"
    aria-label="Zeitraum auswählen"
    class="hidden rounded-lg bg-muted p-[3px] min-[1000px]:inline-flex"
>
    {#each presets as preset (preset.value)}
        <button
            type="button"
            aria-pressed={value === preset.value}
            onclick={() => (value = preset.value)}
            class="rounded-md px-3 py-1 text-sm font-medium whitespace-nowrap transition-colors {value ===
            preset.value
                ? 'bg-background shadow-sm'
                : 'text-muted-foreground hover:text-foreground'}"
        >
            {preset.label}
        </button>
    {/each}
</div>

<div class="min-[1000px]:hidden">
    <Popover.Root bind:open>
        <Popover.Trigger>
            {#snippet child({ props })}
                <Button
                    {...props}
                    variant="outline"
                    class="w-44 justify-between rounded-lg"
                    aria-label="Zeitraum auswählen"
                >
                    {label}
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="ml-2 size-4 opacity-50"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"><path d="m6 9 6 6 6-6" /></svg
                    >
                </Button>
            {/snippet}
        </Popover.Trigger>
        <Popover.Content class="w-52 p-1" align="end">
            {#each presets as preset (preset.value)}
                <button
                    type="button"
                    class="flex w-full items-center justify-between rounded-md px-3 py-1.5 text-sm transition-colors hover:bg-accent {value ===
                    preset.value
                        ? 'font-medium'
                        : ''}"
                    onclick={() => {
                        value = preset.value;
                        open = false;
                    }}
                >
                    {preset.label}
                    {#if value === preset.value}
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="size-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"><path d="M20 6 9 17l-5-5" /></svg
                        >
                    {/if}
                </button>
            {/each}
        </Popover.Content>
    </Popover.Root>
</div>
