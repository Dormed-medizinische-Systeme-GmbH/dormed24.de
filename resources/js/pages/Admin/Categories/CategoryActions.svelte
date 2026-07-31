<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Ellipsis from '@lucide/svelte/icons/ellipsis';
    import Pencil from '@lucide/svelte/icons/pencil';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import * as AdminCategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
    import { Button } from '@/components/ui/button';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuSeparator,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';

    let { category }: { category: { id: number; name: string } } = $props();

    function destroy() {
        if (!confirm(`Kategorie „${category.name}" wirklich löschen?`)) {
            return;
        }

        router.delete(AdminCategoryController.destroy.url(category.id));
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
        <DropdownMenuItem asChild>
            {#snippet children(props)}
                <a
                    class={props.class}
                    href={AdminCategoryController.edit.url(category.id)}
                    onclick={props.onClick}
                >
                    <Pencil class="mr-2 size-4" />
                    Bearbeiten
                </a>
            {/snippet}
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem
            onclick={destroy}
            class="text-destructive focus:text-destructive"
        >
            <Trash2 class="mr-2 size-4" />
            Löschen
        </DropdownMenuItem>
    </DropdownMenuContent>
</DropdownMenu>
