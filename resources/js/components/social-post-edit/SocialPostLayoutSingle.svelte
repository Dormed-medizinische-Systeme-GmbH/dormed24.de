<script lang="ts">
    import type { SocialPostEditLayoutProps } from '@/types';

    let { fields }: SocialPostEditLayoutProps = $props();
</script>

<!-- Variante A: eine Spalte (bisheriges Layout), Vorbereitetes am Ende -->
<div class="flex max-w-2xl flex-col gap-6">
    {@render fields.title()}
    {@render fields.content()}
    {@render fields.platforms()}
    {@render fields.scheduledAt()}

    <div class="flex flex-col gap-6 rounded-lg border border-dashed p-4">
        <p class="text-sm font-medium">Später verfügbar</p>
        {@render fields.media()}
        {@render fields.link()}
        {@render fields.hashtags()}
        {@render fields.linked()}
        {@render fields.platformTexts()}
    </div>
</div>
