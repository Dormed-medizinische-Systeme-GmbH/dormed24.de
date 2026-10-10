<script lang="ts">
    import DisabledField from '@/components/DisabledField.svelte';
    import InputError from '@/components/InputError.svelte';
    import SocialPostLayoutColumns from '@/components/social-post-edit/SocialPostLayoutColumns.svelte';
    import SocialPostLayoutComposer from '@/components/social-post-edit/SocialPostLayoutComposer.svelte';
    import SocialPostLayoutPreview from '@/components/social-post-edit/SocialPostLayoutPreview.svelte';
    import SocialPostLayoutSingle from '@/components/social-post-edit/SocialPostLayoutSingle.svelte';
    import SocialPostLayoutTabs from '@/components/social-post-edit/SocialPostLayoutTabs.svelte';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Textarea } from '@/components/ui/textarea';
    import VariantSwitcher from '@/components/VariantSwitcher.svelte';
    import { SOCIAL_POST_VARIANTS } from '@/lib/socialPostEdit';
    import { useLayoutVariant } from '@/lib/useLayoutVariant.svelte';
    import { cn } from '@/lib/utils';
    import type { SocialPostEditFields, SocialPostEditPreview } from '@/types';

    let {
        title = $bindable(),
        content = $bindable(),
        platforms = $bindable(),
        scheduledAt = $bindable(),
        platformOptions,
        maxContentLength,
        errors,
        disabled = false,
    }: {
        title: string;
        content: string;
        platforms: string[];
        /** Wert für datetime-local in Ortszeit, leer = Entwurf. */
        scheduledAt: string;
        platformOptions: Record<string, string>;
        maxContentLength: number;
        errors: Partial<Record<string, string>>;
        disabled?: boolean;
    } = $props();

    // Layout-Variante: Auswahl steht in der URL (?variant=B); ohne Parameter gilt A.
    const layoutVariant = useLayoutVariant(SOCIAL_POST_VARIANTS);

    function togglePlatform(key: string, checked: boolean) {
        platforms = checked
            ? [...platforms, key]
            : platforms.filter((platform) => platform !== key);
    }

    const platformError = $derived(
        errors.platforms ??
            Object.entries(errors).find(([field]) =>
                field.startsWith('platforms.'),
            )?.[1],
    );

    const preview: SocialPostEditPreview = $derived({
        content,
        platforms,
        platformLabels: platformOptions,
        maxContentLength,
    });

    // Die Snippets werden erst beim Rendern ausgewertet, nicht beim Initialisieren.
    const fields: SocialPostEditFields = $derived({
        title: titleField,
        content: contentField,
        platforms: platformsField,
        scheduledAt: scheduledAtField,
        media: mediaField,
        link: linkField,
        hashtags: hashtagsField,
        linked: linkedField,
        platformTexts: platformTextsField,
    });
</script>

{#snippet titleField()}
    <div class="flex flex-col gap-2">
        <Label for="title">Titel</Label>
        <Input
            id="title"
            bind:value={title}
            maxlength={255}
            placeholder="Nur intern, z. B. „Herbstaktion LinkedIn“"
        />
        <InputError message={errors.title} />
    </div>
{/snippet}

{#snippet contentField(large = false)}
    <div class="flex flex-col gap-2">
        <div class="flex items-baseline justify-between gap-4">
            <Label for="content" class={large ? 'sr-only' : ''}>Text</Label>
            <span
                class="text-xs {content.length > maxContentLength
                    ? 'text-destructive'
                    : 'text-muted-foreground'}"
            >
                {content.length} / {maxContentLength}
            </span>
        </div>
        <Textarea
            id="content"
            bind:value={content}
            rows={large ? 10 : 8}
            class={large ? 'min-h-56 text-base' : 'min-h-40'}
            placeholder="Was soll veröffentlicht werden?"
        />
        <InputError message={errors.content} />
    </div>
{/snippet}

{#snippet platformsField(style: 'checkboxes' | 'pills' = 'checkboxes')}
    <div class="flex flex-col gap-2">
        <span class="text-sm leading-none font-medium">Plattformen</span>
        <div class="flex flex-wrap gap-x-6 gap-y-2">
            {#each Object.entries(platformOptions) as [key, label] (key)}
                {#if style === 'pills'}
                    <button
                        type="button"
                        {disabled}
                        aria-pressed={platforms.includes(key)}
                        onclick={() =>
                            togglePlatform(key, !platforms.includes(key))}
                        class={cn(
                            'rounded-full border px-4 py-1.5 text-sm transition-colors disabled:cursor-not-allowed disabled:opacity-50',
                            platforms.includes(key)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'hover:bg-accent',
                        )}
                    >
                        {label}
                    </button>
                {:else}
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={platforms.includes(key)}
                            onCheckedChange={(checked) =>
                                togglePlatform(key, checked)}
                            {disabled}
                            aria-label={label}
                        />
                        {label}
                    </label>
                {/if}
            {/each}
        </div>
        <InputError message={platformError} />
    </div>
{/snippet}

{#snippet scheduledAtField()}
    <div class="flex flex-col gap-2">
        <Label for="scheduled_at">Geplant für</Label>
        <Input
            id="scheduled_at"
            type="datetime-local"
            bind:value={scheduledAt}
            class="w-fit"
        />
        <p class="text-sm text-muted-foreground">
            Ohne Termin bleibt der Beitrag ein Entwurf.
        </p>
        <InputError message={errors.scheduled_at} />
    </div>
{/snippet}

<!-- Vorbereitete, noch nicht angebundene Felder: gesperrt, nie Teil des Formulars. -->
{#snippet mediaField()}
    <DisabledField
        label="Bild / Medien"
        kind="file"
        hint="Bild oder Video für den Beitrag."
    />
{/snippet}

{#snippet linkField()}
    <DisabledField
        label="Link"
        placeholder="https://…"
        hint="Wird an den Text angehängt."
    />
{/snippet}

{#snippet hashtagsField()}
    <DisabledField label="Hashtags" placeholder="#medizintechnik #pflege" />
{/snippet}

{#snippet linkedField()}
    <DisabledField
        label="Verknüpfter Artikel / Produkt"
        kind="select"
        placeholder="Auswählen"
    />
{/snippet}

{#snippet platformTextsField()}
    <DisabledField
        label="Abweichender Text je Plattform"
        kind="textarea"
        placeholder="Optional: Text nur für eine Plattform anpassen"
    />
{/snippet}

<fieldset class="min-w-0" {disabled}>
    {#if layoutVariant.current === 'B'}
        <SocialPostLayoutColumns {fields} {errors} />
    {:else if layoutVariant.current === 'C'}
        <SocialPostLayoutTabs {fields} {errors} />
    {:else if layoutVariant.current === 'D'}
        <SocialPostLayoutPreview {fields} {errors} {preview} />
    {:else if layoutVariant.current === 'E'}
        <SocialPostLayoutComposer {fields} {errors} />
    {:else}
        <SocialPostLayoutSingle {fields} {errors} />
    {/if}
</fieldset>

<VariantSwitcher
    options={SOCIAL_POST_VARIANTS}
    current={layoutVariant.current}
    onselect={layoutVariant.select}
/>
