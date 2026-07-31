<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import LogOut from '@lucide/svelte/icons/log-out';
    import User from '@lucide/svelte/icons/user';
    import {
        DropdownMenuGroup,
        DropdownMenuItem,
        DropdownMenuLabel,
        DropdownMenuSeparator,
    } from '@/components/ui/dropdown-menu';
    import { toUrl } from '@/lib/utils';
    import { logout } from '@/routes';
    import { edit } from '@/routes/profile';
    import type { Customer } from '@/types';

    let {
        user,
    }: {
        user: Customer;
    } = $props();

    function handleLogout(propsOnClick?: () => void) {
        return () => {
            propsOnClick?.();
            router.flushAll();
        };
    }
</script>

<DropdownMenuLabel class="p-0 font-normal">
    <div class="grid px-1 py-1.5 text-left text-sm leading-tight">
        <span class="truncate font-medium">{user.name}</span>
        <span class="truncate text-xs text-muted-foreground">{user.email}</span>
    </div>
</DropdownMenuLabel>
<DropdownMenuSeparator />
<DropdownMenuGroup>
    <DropdownMenuItem asChild>
        {#snippet children(props)}
            <Link
                class={props.class}
                href={toUrl(edit())}
                prefetch
                onclick={props.onClick}
            >
                <User class="mr-2 h-4 w-4" />
                Mein Profil
            </Link>
        {/snippet}
    </DropdownMenuItem>
</DropdownMenuGroup>
<DropdownMenuSeparator />
<DropdownMenuItem asChild>
    {#snippet children(props)}
        <Link
            class={props.class}
            href={logout()}
            as="button"
            onclick={handleLogout(props.onClick)}
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </Link>
    {/snippet}
</DropdownMenuItem>
