<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import LogOut from '@lucide/svelte/icons/log-out';
    import * as AdminLoginController from '@/actions/App/Http/Controllers/Admin/LoginController';
    import { Button } from '@/components/ui/button';
    import { SidebarMenu, SidebarMenuItem } from '@/components/ui/sidebar';

    const admin = $derived((page.props.auth as any).admin);

    function logout() {
        router.post(AdminLoginController.logout.url());
    }
</script>

{#if admin}
    <SidebarMenu>
        <SidebarMenuItem>
            <div
                class="flex items-center gap-2 p-2 group-data-[collapsible=icon]:p-0"
            >
                <div
                    class="grid min-w-0 flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden"
                >
                    <span class="truncate font-medium">{admin.name}</span>
                    <span class="truncate text-xs text-muted-foreground"
                        >{admin.email}</span
                    >
                </div>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    class="ml-auto shrink-0 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                    aria-label="Abmelden"
                    title="Abmelden"
                    onclick={logout}
                >
                    <LogOut class="size-4" />
                </Button>
            </div>
        </SidebarMenuItem>
    </SidebarMenu>
{/if}
