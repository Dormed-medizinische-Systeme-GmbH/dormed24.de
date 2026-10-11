<script lang="ts">
    import AppFooter from '@/components/AppFooter.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ShopHeader from '@/components/ShopHeader.svelte';
</script>

<AppHead
    title="Zahlungsarten"
    description="Akzeptierte Zahlungsmethoden im dormed24-Shop: PayPal, Lastschrift, Kreditkarte und Kauf auf Rechnung."
/>

<div class="flex min-h-screen flex-col bg-white">
    <ShopHeader />

    <main class="flex-1 mx-auto max-w-7xl px-4 py-10 lg:px-8">
        <h1 class="mb-6 text-2xl font-bold text-brand-secondary">Zahlung</h1>
    </main>

    <AppFooter />
</div>
