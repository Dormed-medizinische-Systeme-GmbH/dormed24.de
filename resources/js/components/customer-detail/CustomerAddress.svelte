<script lang="ts">
    import { addressTypeLabels } from '@/lib/customerDetail';
    import type { CustomerAddress } from '@/types';

    let { address }: { address: CustomerAddress } = $props();
</script>

<div class="rounded-lg border bg-gray-50 p-4 text-sm">
    <div class="mb-2 flex items-center gap-2">
        <span
            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
        >
            {addressTypeLabels[address.type] ?? address.type}
        </span>
        {#if address.is_default}
            <span
                class="rounded-full bg-[#1a6bbf]/10 px-2 py-0.5 text-xs text-[#1a6bbf]"
                >Standard</span
            >
        {/if}
    </div>
    {#if address.company}<p class="font-medium">{address.company}</p>{/if}
    <p>{address.first_name + ' ' + address.last_name}</p>
    <p>{address.street} {address.house_number}</p>
    {#if address.address_line2}<p>{address.address_line2}</p>{/if}
    <p>{address.zip} {address.city}</p>
    {#if address.phone}<p class="mt-1 text-muted-foreground">
            {address.phone}
        </p>{/if}
</div>
