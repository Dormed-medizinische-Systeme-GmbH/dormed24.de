<script lang="ts">
    import type {
        SocialPostEditLayoutProps,
        SocialPostEditPreview,
    } from '@/types';

    let {
        fields,
        preview,
    }: SocialPostEditLayoutProps & { preview: SocialPostEditPreview } =
        $props();
</script>

<!-- Variante D: Formular links, pro gewählter Plattform eine Vorschau rechts -->
<div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
    <div class="flex min-w-0 max-w-2xl flex-col gap-6">
        {@render fields.title()}
        {@render fields.content()}
        {@render fields.platforms()}
        {@render fields.scheduledAt()}
        {@render fields.media()}
        {@render fields.link()}
        {@render fields.hashtags()}
        {@render fields.linked()}
        {@render fields.platformTexts()}
    </div>

    <aside class="flex flex-col gap-3 xl:sticky xl:top-0">
        <p class="text-xs text-muted-foreground">
            Vorschau je Plattform (vereinfacht)
        </p>
        {#each preview.platforms as platform (platform)}
            <article
                class="flex flex-col gap-2 rounded-xl border bg-white p-4"
                aria-label="Vorschau {preview.platformLabels[platform] ??
                    platform}"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="text-sm font-semibold">
                        {preview.platformLabels[platform] ?? platform}
                    </span>
                    <span
                        class="text-xs {preview.content.length >
                        preview.maxContentLength
                            ? 'text-destructive'
                            : 'text-muted-foreground'}"
                    >
                        {preview.content.length} / {preview.maxContentLength}
                    </span>
                </div>
                <p class="text-sm break-words whitespace-pre-line">
                    {preview.content || 'Noch kein Text.'}
                </p>
            </article>
        {:else}
            <p
                class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                Wähle mindestens eine Plattform, um die Vorschau zu sehen.
            </p>
        {/each}
    </aside>
</div>
