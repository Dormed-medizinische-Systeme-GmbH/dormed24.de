<script lang="ts">
    import type { SocialPostEditLayoutProps } from '@/types';

    let { fields }: SocialPostEditLayoutProps = $props();
</script>

<!-- Variante E: Composer – Text zuerst, Plattformen als Chips, Termin daneben -->
<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    {@render fields.content(true)}

    <div class="grid gap-6 md:grid-cols-[minmax(0,1fr)_auto] md:items-start">
        {@render fields.platforms('pills')}
        {@render fields.scheduledAt()}
    </div>

    {@render fields.title()}

    <details class="rounded-lg border p-4">
        <summary class="cursor-pointer text-sm font-medium">
            Später verfügbar: Medien, Link, Hashtags, Verknüpfung
        </summary>
        <div class="mt-4 flex flex-col gap-6">
            {@render fields.media()}
            {@render fields.link()}
            {@render fields.hashtags()}
            {@render fields.linked()}
            {@render fields.platformTexts()}
        </div>
    </details>
</div>
