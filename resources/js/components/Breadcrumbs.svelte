<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import {
        Breadcrumb,
        BreadcrumbItem,
        BreadcrumbLink,
        BreadcrumbList,
        BreadcrumbPage,
    } from '@/components/ui/breadcrumb';
    import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

    let {
        breadcrumbs = [],
        currentClass = '',
    }: {
        breadcrumbs: BreadcrumbItemType[];
        currentClass?: string;
    } = $props();
</script>

<Breadcrumb>
    <BreadcrumbList>
        {#each breadcrumbs as item, index (item.href)}
            <BreadcrumbItem>
                {#if index === breadcrumbs.length - 1}
                    <BreadcrumbPage>
                        <span class={currentClass}>{item.title}</span>
                    </BreadcrumbPage>
                {:else}
                    <BreadcrumbLink asChild>
                        {#snippet children(props)}
                            <Link href={item.href} class={props.class}>
                                {item.title}
                            </Link>
                        {/snippet}
                    </BreadcrumbLink>
                {/if}
            </BreadcrumbItem>
            {#if index !== breadcrumbs.length - 1}
                <li role="presentation" aria-hidden="true">
                    <ChevronRight class="size-3.5 text-muted-foreground" />
                </li>
            {/if}
        {/each}
    </BreadcrumbList>
</Breadcrumb>
