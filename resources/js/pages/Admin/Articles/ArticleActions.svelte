<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Ellipsis from '@lucide/svelte/icons/ellipsis';
    import Pencil from '@lucide/svelte/icons/pencil';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import * as ArticleController from '@/actions/App/Http/Controllers/Admin/ArticleController';
    import { Button } from '@/components/ui/button';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuSeparator,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';

    let {
        article,
        showEdit = true,
    }: {
        article: { id: number; title: string };
        /** Auf der Bearbeiten-Seite selbst ist „Bearbeiten" überflüssig. */
        showEdit?: boolean;
    } = $props();

    function destroy() {
        if (!confirm(`Artikel „${article.title}" wirklich löschen?`)) {
            return;
        }

        router.delete(ArticleController.destroy.url(article.id));
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
        {#if showEdit}
            <DropdownMenuItem asChild>
                {#snippet children(props)}
                    <a
                        class={props.class}
                        href={ArticleController.edit.url(article.id)}
                        onclick={props.onClick}
                    >
                        <Pencil class="mr-2 size-4" />
                        Bearbeiten
                    </a>
                {/snippet}
            </DropdownMenuItem>
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
