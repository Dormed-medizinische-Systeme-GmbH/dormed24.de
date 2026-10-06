<script lang="ts">
    import type { Snippet } from 'svelte';
    import AppLayout from '@/layouts/app/AppSidebarLayout.svelte';
    import type { BreadcrumbItem } from '@/types';

    let {
        breadcrumbs = [],
        actions,
        children,
    }: {
        breadcrumbs?: BreadcrumbItem[];
        /** Header-Aktionen; Seiten setzen sie per setLayoutProps({ actions }). */
        actions?: Snippet;
        children?: Snippet;
    } = $props();
</script>

<AppLayout {breadcrumbs} {actions}>
    {@render children?.()}
</AppLayout>
