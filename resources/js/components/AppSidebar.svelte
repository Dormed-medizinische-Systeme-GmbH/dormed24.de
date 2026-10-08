<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import Building2 from '@lucide/svelte/icons/building-2';
    import LayoutGrid from '@lucide/svelte/icons/layout-grid';
    import Mail from '@lucide/svelte/icons/mail';
    import Package from '@lucide/svelte/icons/package';
    import Settings from '@lucide/svelte/icons/settings';
    import Share2 from '@lucide/svelte/icons/share-2';
    import ShoppingCart from '@lucide/svelte/icons/shopping-cart';
    import Truck from '@lucide/svelte/icons/truck';
    import Users from '@lucide/svelte/icons/users';
    import type { Snippet } from 'svelte';
    import AppLogo from '@/components/AppLogo.svelte';
    import NavAdmin from '@/components/NavAdmin.svelte';
    import NavMain from '@/components/NavMain.svelte';
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import type { NavItem } from '@/types';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    const mainNavItems: NavItem[] = [
        { title: 'Dashboard', href: '/admin', icon: LayoutGrid },
        { title: 'Kunden', href: '/admin/customers', icon: Users },
        { title: 'Bestellungen', href: '/admin/orders', icon: ShoppingCart },
    ];

    const catalogItems: NavItem[] = [
        { title: 'Hersteller', href: '/admin/manufacturers', icon: Building2 },
        { title: 'Produkte', href: '/admin/products', icon: Package },
    ];

    const marketingItems: NavItem[] = [
        { title: 'Newsletter', href: '/admin/newsletters', icon: Mail },
        { title: 'Social-Media', href: '/admin/social-posts', icon: Share2 },
    ];

    const settingsItems: NavItem[] = [
        { title: 'Allgemein', href: '/admin/settings/general', icon: Settings },
        {
            title: 'Versandarten',
            href: '/admin/settings/shipping',
            icon: Truck,
        },
    ];
</script>

<Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg" asChild>
                    {#snippet children(props)}
                        <Link {...props} href="/admin" class={props.class}>
                            <AppLogo />
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
        <NavMain items={mainNavItems} />
        <NavMain title="Katalog" items={catalogItems} />
        <NavMain title="Marketing" items={marketingItems} />
        <NavMain title="Einstellungen" items={settingsItems} />
    </SidebarContent>

    <SidebarFooter>
        <NavAdmin />
    </SidebarFooter>
</Sidebar>
{@render children?.()}
