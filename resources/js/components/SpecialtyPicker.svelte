<script lang="ts">
    import { Checkbox } from '@/components/ui/checkbox';
    import { Label } from '@/components/ui/label';

    type Specialty = { slug: string; name: string };

    let {
        selected = $bindable(),
        options,
        errors = {},
    }: {
        selected: string[];
        options: Specialty[];
        errors?: Partial<Record<string, string>>;
    } = $props();

    const errorMessages = $derived(
        Object.entries(errors)
            .filter(([key]) => key.startsWith('specialties'))
            .map(([, message]) => message),
    );

    function toggle(slug: string, checked: boolean) {
        selected = checked
            ? [...selected, slug]
            : selected.filter((value) => value !== slug);
    }
</script>

<fieldset class="flex flex-col gap-2">
    <legend class="mb-2">
        <Label>Fachrichtungen</Label>
        <p class="text-xs text-muted-foreground">
            Mehrfachauswahl. Grundlage für den Fachrichtungs-Filter im Shop.
        </p>
    </legend>

    <div class="grid gap-2 sm:grid-cols-2">
        {#each options as option (option.slug)}
            <label
                for={`specialty-${option.slug}`}
                class="flex cursor-pointer items-center gap-2 text-sm"
            >
                <Checkbox
                    id={`specialty-${option.slug}`}
                    checked={selected.includes(option.slug)}
                    onCheckedChange={(value) => toggle(option.slug, value)}
                />
                {option.name}
            </label>
        {/each}
    </div>

    {#each errorMessages as message, index (index)}
        <p class="text-sm text-destructive">{message}</p>
    {/each}
</fieldset>
