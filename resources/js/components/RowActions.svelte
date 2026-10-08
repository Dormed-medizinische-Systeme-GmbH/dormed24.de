<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Ellipsis from '@lucide/svelte/icons/ellipsis';
    import Pencil from '@lucide/svelte/icons/pencil';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import { Button } from '@/components/ui/button';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuSeparator,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';

    type RowLink = {
        href: string;
        label: string;
        icon: typeof Pencil;
        /** Öffnet in einem neuen Tab, z. B. die Shop-Ansicht. */
        external?: boolean;
    };

    let {
        editHref,
        links = [],
        destroyHref,
        confirmMessage = 'Wirklich löschen?',
    }: {
        /** Ohne Link kein „Bearbeiten“, z. B. auf der Bearbeiten-Seite selbst. */
        editHref?: string;
        /** Weitere Links zwischen „Bearbeiten“ und „Löschen“. */
        links?: RowLink[];
        /** Ohne Adresse kein „Löschen“, z. B. für verschickte Newsletter. */
        destroyHref?: string;
        confirmMessage?: string;
    } = $props();

    const allLinks = $derived([
        ...(editHref
            ? [{ href: editHref, label: 'Bearbeiten', icon: Pencil }]
            : []),
        ...links,
    ] as RowLink[]);

    function destroy() {
        if (!destroyHref || !confirm(confirmMessage)) {
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
        {#each allLinks as link (link.href)}
            <DropdownMenuItem asChild>
                {#snippet children(props)}
                    <a
                        class={props.class}
                        href={link.href}
                        target={link.external ? '_blank' : undefined}
                        rel={link.external ? 'noopener noreferrer' : undefined}
                        onclick={props.onClick}
                    >
                        <link.icon class="mr-2 size-4" />
                        {link.label}
                    </a>
                {/snippet}
            </DropdownMenuItem>
        {/each}
        {#if destroyHref}
            {#if allLinks.length > 0}
                <DropdownMenuSeparator />
            {/if}
            <DropdownMenuItem
                onclick={destroy}
                class="text-destructive focus:text-destructive"
            >
                <Trash2 class="mr-2 size-4" />
                Löschen
            </DropdownMenuItem>
        {/if}
    </DropdownMenuContent>
</DropdownMenu>
