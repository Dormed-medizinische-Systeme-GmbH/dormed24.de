<script module lang="ts">
    import AppLayout from '@/layouts/app/AppSidebarLayout.svelte';

    export const layout = AppLayout;
    export const breadcrumbs = [
        { title: 'Kunden', href: '/admin/customers' },
        { title: 'Kundendetail' },
    ];
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import CustomerLayoutCards from '@/components/customer-detail/CustomerLayoutCards.svelte';
    import CustomerLayoutProfile from '@/components/customer-detail/CustomerLayoutProfile.svelte';
    import CustomerLayoutSidebar from '@/components/customer-detail/CustomerLayoutSidebar.svelte';
    import CustomerLayoutTabs from '@/components/customer-detail/CustomerLayoutTabs.svelte';
    import CustomerLayoutTimeline from '@/components/customer-detail/CustomerLayoutTimeline.svelte';
    import VariantSwitcher from '@/components/VariantSwitcher.svelte';
    import { CUSTOMER_VARIANTS } from '@/lib/customerDetail';
    import { useLayoutVariant } from '@/lib/useLayoutVariant.svelte';
    import type { CustomerDetail } from '@/types';

    let { customer }: { customer: CustomerDetail } = $props();

    // Layout-Variante: Auswahl steht in der URL (?variant=B); ohne Parameter gilt A.
    const layoutVariant = useLayoutVariant(CUSTOMER_VARIANTS);
</script>

<AppHead title="{customer.name} — Kunden — Admin" />

{#if layoutVariant.current === 'B'}
    <CustomerLayoutProfile {customer} />
{:else if layoutVariant.current === 'C'}
    <CustomerLayoutSidebar {customer} />
{:else if layoutVariant.current === 'D'}
    <CustomerLayoutTabs {customer} />
{:else if layoutVariant.current === 'E'}
    <CustomerLayoutTimeline {customer} />
{:else}
    <CustomerLayoutCards {customer} />
{/if}

<VariantSwitcher
    options={CUSTOMER_VARIANTS}
    current={layoutVariant.current}
    onselect={layoutVariant.select}
/>
