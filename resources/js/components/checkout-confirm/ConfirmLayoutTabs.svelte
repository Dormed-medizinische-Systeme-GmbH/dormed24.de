<script lang="ts">
    import { Button } from '@/components/ui/button';
    import * as Tabs from '@/components/ui/tabs';
    import type { CheckoutConfirmLayoutProps } from '@/types';

    let { fields }: CheckoutConfirmLayoutProps = $props();

    let tab = $state('adressen');
</script>

<!-- Variante C: Adressen und Prüfen & Bezahlen in zwei Reitern -->
<Tabs.Root bind:value={tab}>
    <Tabs.List>
        <Tabs.Trigger value="adressen">1 · Adressen</Tabs.Trigger>
        <Tabs.Trigger value="bezahlen">2 · Prüfen &amp; bezahlen</Tabs.Trigger>
    </Tabs.List>

    <Tabs.Content value="adressen">
        <div class="mt-6 grid gap-6 lg:grid-cols-2 lg:items-start">
            <div class="rounded-lg border bg-white p-5">
                {@render fields.shippingAddress()}
            </div>
            <div class="rounded-lg border bg-white p-5">
                {@render fields.billingAddress()}
            </div>
        </div>
        <div class="mt-6 flex justify-end">
            <Button onclick={() => (tab = 'bezahlen')}>
                Weiter zu Prüfen &amp; bezahlen
            </Button>
        </div>
    </Tabs.Content>

    <Tabs.Content value="bezahlen">
        <div class="mx-auto mt-6 flex max-w-xl flex-col gap-6">
            <div class="rounded-lg border bg-white p-5">
                {@render fields.terms()}
            </div>
            <div class="rounded-lg border bg-white p-5">
                {@render fields.totals()}
                <div class="mt-6">
                    {@render fields.pay()}
                </div>
            </div>
            {@render fields.note()}
        </div>
    </Tabs.Content>
</Tabs.Root>
