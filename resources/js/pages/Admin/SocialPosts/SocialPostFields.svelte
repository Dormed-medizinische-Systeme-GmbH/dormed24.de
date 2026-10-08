<script lang="ts">
    import InputError from '@/components/InputError.svelte';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Textarea } from '@/components/ui/textarea';

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

    function togglePlatform(key: string, checked: boolean) {
        platforms = checked
            ? [...platforms, key]
            : platforms.filter((platform) => platform !== key);
    }
</script>

<fieldset class="flex flex-col gap-6" {disabled}>
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

    <div class="flex flex-col gap-2">
        <div class="flex items-baseline justify-between gap-4">
            <Label for="content">Text</Label>
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
            rows={8}
            class="min-h-40"
            placeholder="Was soll veröffentlicht werden?"
        />
        <InputError message={errors.content} />
    </div>

    <div class="flex flex-col gap-2">
        <span class="text-sm leading-none font-medium">Plattformen</span>
        <div class="flex flex-wrap gap-6">
            {#each Object.entries(platformOptions) as [key, label] (key)}
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
            {/each}
        </div>
        <InputError
            message={errors.platforms ??
                Object.entries(errors).find(([field]) =>
                    field.startsWith('platforms.'),
                )?.[1]}
        />
    </div>

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
</fieldset>
