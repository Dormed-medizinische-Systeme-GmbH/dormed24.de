<script lang="ts">
    import { Link } from '@inertiajs/svelte';

    let { href = '', label = '' }: { href: string; label: string } = $props();
</script>

<Link {href} class="text-[#1a6bbf] hover:underline">{label}</Link>
