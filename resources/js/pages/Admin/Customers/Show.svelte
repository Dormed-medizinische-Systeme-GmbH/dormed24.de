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
    import CustomerDetailLayout from '@/components/customer-detail/CustomerDetailLayout.svelte';
    import type { CustomerDetail } from '@/types';

    let { customer }: { customer: CustomerDetail } = $props();
</script>

<AppHead title="{customer.name} — Kunden — Admin" />

<CustomerDetailLayout {customer} />
