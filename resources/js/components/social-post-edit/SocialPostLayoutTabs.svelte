<script lang="ts">
    import * as Tabs from '@/components/ui/tabs';
    import type { SocialPostEditLayoutProps } from '@/types';

    let { fields, errors }: SocialPostEditLayoutProps = $props();

    // Fehler in einem nicht aktiven Reiter sichtbar machen.
    const hasError = (keys: string[]) =>
        Object.keys(errors).some((field) =>
            keys.some((key) => field === key || field.startsWith(`${key}.`)),
        );
</script>

{#snippet errorDot(show: boolean)}
    {#if show}
        <span
            class="ml-1.5 size-2 rounded-full bg-destructive"
            role="img"
            aria-label="Enthält Fehler"
        ></span>
    {/if}
{/snippet}

<!-- Variante C: Inhalt, Veröffentlichung und Vorbereitetes in Reitern -->
<Tabs.Root value="inhalt">
    <Tabs.List>
        <Tabs.Trigger value="inhalt">
            Inhalt
            {@render errorDot(hasError(['title', 'content']))}
        </Tabs.Trigger>
        <Tabs.Trigger value="veroeffentlichung">
            Veröffentlichung
            {@render errorDot(hasError(['platforms', 'scheduled_at']))}
        </Tabs.Trigger>
        <Tabs.Trigger value="erweitert">Erweitert</Tabs.Trigger>
    </Tabs.List>

    <Tabs.Content value="inhalt">
        <div class="mt-6 flex max-w-2xl flex-col gap-6">
            {@render fields.title()}
            {@render fields.content()}
        </div>
    </Tabs.Content>

    <Tabs.Content value="veroeffentlichung">
        <div class="mt-6 flex max-w-xl flex-col gap-6">
            {@render fields.platforms()}
            {@render fields.scheduledAt()}
        </div>
    </Tabs.Content>

    <Tabs.Content value="erweitert">
        <div class="mt-6 flex max-w-xl flex-col gap-6">
            {@render fields.media()}
            {@render fields.link()}
            {@render fields.hashtags()}
            {@render fields.linked()}
            {@render fields.platformTexts()}
        </div>
    </Tabs.Content>
</Tabs.Root>
