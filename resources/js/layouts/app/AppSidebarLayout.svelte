<script lang="ts">
    import type { Snippet } from 'svelte';
    import AppContent from '@/components/AppContent.svelte';
    import AppShell from '@/components/AppShell.svelte';
    import AppSidebar from '@/components/AppSidebar.svelte';
    import AppSidebarHeader from '@/components/AppSidebarHeader.svelte';
    import { Toaster } from '@/components/ui/sonner';
    import type { BreadcrumbItem } from '@/types';

    let {
        breadcrumbs = [],
        actions,
        children,
    }: {
        breadcrumbs?: BreadcrumbItem[];
        actions?: Snippet;
        children?: Snippet;
    } = $props();
</script>

<AppShell variant="sidebar">
    <AppSidebar />
    <!--
        Fester Header: Der Inhaltsbereich ist genau fensterhoch (md: abzüglich
        des 2×0,5rem-Rands der inset-Variante), der Header bleibt stehen und nur
        der Bereich darunter scrollt.
    -->
    <AppContent
        variant="sidebar"
        class="h-svh overflow-hidden md:h-[calc(100svh-1rem)]"
    >
        <AppSidebarHeader {breadcrumbs} {actions} />
        <div class="flex min-h-0 flex-1 flex-col overflow-y-auto">
            {@render children?.()}
        </div>
    </AppContent>
    <Toaster />
</AppShell>
