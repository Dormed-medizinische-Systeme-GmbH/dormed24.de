<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Ellipsis from '@lucide/svelte/icons/ellipsis';
    import Pencil from '@lucide/svelte/icons/pencil';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import type { Snippet } from 'svelte';
    import { Button } from '@/components/ui/button';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuSeparator,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';

    let {
        editHref,
        destroyHref,
        confirmMessage,
        items,
    }: {
        /** Ohne Link kein „Bearbeiten“, z. B. auf der Bearbeiten-Seite selbst. */
        editHref?: string;
        destroyHref: string;
        confirmMessage: string;
        /** Weitere Einträge zwischen „Bearbeiten“ und „Löschen“. */
        items?: Snippet;
    } = $props();

    function destroy() {
        if (!confirm(confirmMessage)) {
            return;
        }

        router.delete(destroyHref);
    }
</script>

<DropdownMenu>
    <DropdownMenuTrigger asChild>
        {#snippet children(props)}
            <Button
                {...props}
                variant="ghost"
                size="icon"
                class="size-8"
                aria-label="Aktionen"
            >
                <Ellipsis class="size-4" />
            </Button>
        {/snippet}
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end">
        {#if editHref}
            <DropdownMenuItem asChild>
                {#snippet children(props)}
                    <a
                        class={props.class}
                        href={editHref}
                        onclick={props.onClick}
                    >
                        <Pencil class="mr-2 size-4" />
                        Bearbeiten
                    </a>
                {/snippet}
            </DropdownMenuItem>
        {/if}
        {@render items?.()}
        {#if editHref || items}
            <DropdownMenuSeparator />
        {/if}
        <DropdownMenuItem
            onclick={destroy}
            class="text-destructive focus:text-destructive"
        >
            <Trash2 class="mr-2 size-4" />
            Löschen
        </DropdownMenuItem>
    </DropdownMenuContent>
</DropdownMenu>
