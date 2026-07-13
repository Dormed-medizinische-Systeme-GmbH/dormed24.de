<script lang="ts">
    import { cn } from '@/lib/utils';
    import Check from '@lucide/svelte/icons/check';

    let {
        checked = $bindable(false),
        indeterminate = false,
        disabled = false,
        onCheckedChange,
        class: className = '',
        id,
        name,
        value,
        ...rest
    }: {
        checked?: boolean;
        indeterminate?: boolean;
        disabled?: boolean;
        onCheckedChange?: (checked: boolean) => void;
        class?: string;
        id?: string;
        name?: string;
        value?: string;
        [key: string]: unknown;
    } = $props();
</script>

<button
    type="button"
    role="checkbox"
    aria-checked={indeterminate ? 'mixed' : checked}
    data-state={indeterminate ? 'indeterminate' : checked ? 'checked' : 'unchecked'}
    data-slot="checkbox"
    {disabled}
    {id}
    class={cn(
        'peer border-input data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-lg border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50',
        className,
    )}
    onclick={() => {
        if (!disabled) {
            checked = indeterminate ? true : !checked;
            onCheckedChange?.(checked);
        }
    }}
    {...rest}
>
    {#if checked || indeterminate}
        <div data-slot="checkbox-indicator" class="grid place-content-center text-current transition-none">
            {#if indeterminate}
                <div class="size-2 rounded-sm bg-current"></div>
            {:else}
                <Check class="size-3.5" />
            {/if}
        </div>
    {/if}
</button>
{#if name}
    <input type="hidden" {name} value={checked ? (value ?? 'on') : ''} />
{/if}
