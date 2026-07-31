<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import ChevronsUpDown from '@lucide/svelte/icons/chevrons-up-down';
    import LogOut from '@lucide/svelte/icons/log-out';
    import * as AdminLoginController from '@/actions/App/Http/Controllers/Admin/LoginController';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuLabel,
        DropdownMenuSeparator,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import {
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
        useSidebar,
    } from '@/components/ui/sidebar';

    const admin = $derived((page.props.auth as any).admin);
    const { isMobile, state: sidebarState } = useSidebar();

    function logout() {
        router.post(AdminLoginController.logout.url());
    }
</script>

{#if admin}
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu class="w-full">
                <DropdownMenuTrigger asChild>
                    {#snippet children(props)}
                        <SidebarMenuButton
                            size="lg"
                            class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                            onclick={props.onclick}
                            aria-expanded={props['aria-expanded']}
                            data-state={props['data-state']}
                        >
                            <div
                                class="grid flex-1 text-left text-sm leading-tight"
                            >
                                <span class="truncate font-medium"
                                    >{admin.name}</span
                                >
                                <span
                                    class="truncate text-xs text-muted-foreground"
                                    >{admin.email}</span
                                >
                            </div>
                            <ChevronsUpDown class="ml-auto size-4" />
                        </SidebarMenuButton>
                    {/snippet}
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-full min-w-0 rounded-lg"
                    side={$sidebarState === 'collapsed' && !$isMobile
                        ? 'left'
                        : 'top'}
                    align="end"
                    sideOffset={4}
                >
                    <DropdownMenuLabel class="p-0 font-normal">
                        <div class="px-2 py-1.5 text-left text-sm">
                            <span class="block font-medium">{admin.name}</span>
                            <span class="block text-xs text-muted-foreground"
                                >{admin.email}</span
                            >
                        </div>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem asChild>
                        {#snippet children(props)}
                            <button
                                type="button"
                                class={props.class}
                                onclick={() => {
                                    props.onClick?.();
                                    logout();
                                }}
                            >
                                <LogOut class="mr-2 size-4" />
                                Abmelden
                            </button>
                        {/snippet}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
{/if}
