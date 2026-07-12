<script lang="ts">
    import { onMount } from 'svelte';

    // Approximation only: no real cookie-consent logic yet (both buttons do
    // the same thing), just dismiss-once-per-browser-session. sessionStorage
    // on purpose, not localStorage - this isn't meant to persist forever.
    const STORAGE_KEY = 'cookie-banner-dismissed';

    let visible = $state(false);

    onMount(() => {
        visible = sessionStorage.getItem(STORAGE_KEY) !== '1';
    });

    function hideBanner() {
        visible = false;
        sessionStorage.setItem(STORAGE_KEY, '1');
    }
</script>

{#if visible}
    <div
        class="fixed inset-x-0 bottom-0 z-50 border-t bg-white px-4 py-2.5 shadow-[0_-10px_30px_rgba(15,23,42,0.12)]"
    >
        <div
            class="mx-auto flex max-w-7xl flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="max-w-3xl">
                <p class="mt-0.5 text-sm leading-5 text-muted-foreground">
                    Wir verwenden Cookies, um den Shop bereitzustellen und
                    später optionale Dienste einbinden zu können.
                </p>
            </div>

            <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                <button
                    type="button"
                    class="rounded-lg border px-4 py-2 text-sm font-semibold text-brand-secondary transition hover:bg-gray-50"
                    onclick={hideBanner}
                >
                    Nur notwendige
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary/90"
                    onclick={hideBanner}
                >
                    Alle Cookies erlauben
                </button>
            </div>
        </div>
    </div>
{/if}
