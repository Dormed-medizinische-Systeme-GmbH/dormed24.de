<script lang="ts">
    import CircleAlert from '@lucide/svelte/icons/circle-alert';
    import { fly } from 'svelte/transition';
    import AlertError from '@/components/AlertError.svelte';
    import { Alert, AlertTitle } from '@/components/ui/alert';

    let {
        errors = {},
        title = 'Es sind Fehler aufgetreten.',
        showDetails = true,
        autoDismissMs = 6000,
    }: {
        errors: Record<string, string | undefined>;
        title?: string;
        showDetails?: boolean;
        autoDismissMs?: number;
    } = $props();

    const messages = $derived(
        Object.values(errors).filter((message): message is string =>
            Boolean(message),
        ),
    );

    let dismissed = $state(false);

    $effect(() => {
        dismissed = false;

        if (messages.length === 0) {
            return;
        }

        const timeout = setTimeout(() => {
            dismissed = true;
        }, autoDismissMs);

        return () => clearTimeout(timeout);
    });
</script>

{#if messages.length > 0 && !dismissed}
    <div
        class="fixed right-4 bottom-4 left-4 z-50 sm:left-auto sm:w-full sm:max-w-sm"
        transition:fly={{ y: 16, duration: 200 }}
    >
        {#if showDetails}
            <AlertError errors={messages} {title} />
        {:else}
            <Alert variant="destructive">
                <CircleAlert class="size-4" />
                <AlertTitle>{title}</AlertTitle>
            </Alert>
        {/if}
    </div>
{/if}
