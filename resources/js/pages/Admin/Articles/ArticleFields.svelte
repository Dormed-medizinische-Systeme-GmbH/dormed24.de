<script lang="ts">
    import InputError from '@/components/InputError.svelte';
    import { Card, CardContent } from '@/components/ui/card';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import {
        NativeSelect,
        NativeSelectOption,
    } from '@/components/ui/native-select';
    import { Textarea } from '@/components/ui/textarea';

    let {
        title = $bindable(),
        slug = $bindable(),
        category = $bindable(),
        teaser = $bindable(),
        content = $bindable(),
        publishedAt = $bindable(),
        categories,
        errors,
    }: {
        title: string;
        /** Leer = wird aus dem Titel erzeugt. */
        slug: string;
        category: string;
        teaser: string;
        content: string;
        /** Wert für datetime-local in Ortszeit, leer = Entwurf. */
        publishedAt: string;
        categories: Record<string, string>;
        errors: Partial<Record<string, string>>;
    } = $props();
</script>

<div
    class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:items-start"
>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <Label for="title">Titel</Label>
            <Input id="title" bind:value={title} maxlength={255} />
            <InputError message={errors.title} />
        </div>

        <div class="flex flex-col gap-2">
            <Label for="slug">Adresse</Label>
            <div
                class="flex items-center overflow-hidden rounded-md border focus-within:ring-2 focus-within:ring-ring"
            >
                <span
                    class="border-r bg-muted px-3 py-2 text-sm text-muted-foreground"
                    >/magazin/</span
                >
                <input
                    id="slug"
                    bind:value={slug}
                    maxlength={255}
                    placeholder="wird aus dem Titel erzeugt"
                    class="min-w-0 flex-1 bg-transparent px-3 py-2 text-sm outline-none"
                />
            </div>
            <p class="text-sm text-muted-foreground">
                Nach dem Veröffentlichen besser nicht mehr ändern, sonst laufen
                Links ins Leere.
            </p>
            <InputError message={errors.slug} />
        </div>

        <div class="flex flex-col gap-2">
            <Label for="teaser">Teaser</Label>
            <Textarea
                id="teaser"
                bind:value={teaser}
                rows={3}
                maxlength={300}
                placeholder="Kurzbeschreibung für Übersicht und Suchmaschinen"
            />
            <InputError message={errors.teaser} />
        </div>

        <div class="flex flex-col gap-2">
            <Label for="content">Inhalt</Label>
            <Textarea
                id="content"
                bind:value={content}
                rows={18}
                class="min-h-96 font-mono text-sm"
            />
            <p class="text-sm text-muted-foreground">
                Markdown: <code>## Überschrift</code>, <code>- Liste</code>,
                <code>**fett**</code>,
                <code>[Linktext](https://…)</code>. Leerzeile = neuer Absatz.
            </p>
            <InputError message={errors.content} />
        </div>
    </div>

    <Card>
        <CardContent class="flex flex-col gap-6">
            <div class="flex flex-col gap-2">
                <Label for="category">Kategorie</Label>
                <NativeSelect
                    id="category"
                    bind:value={category}
                    class="w-full"
                >
                    {#each Object.entries(categories) as [key, label] (key)}
                        <NativeSelectOption value={key}
                            >{label}</NativeSelectOption
                        >
                    {/each}
                </NativeSelect>
                <InputError message={errors.category} />
            </div>

            <div class="flex flex-col gap-2">
                <Label for="published_at">Veröffentlichen am</Label>
                <Input
                    id="published_at"
                    type="datetime-local"
                    bind:value={publishedAt}
                />
                <p class="text-sm text-muted-foreground">
                    Leer = Entwurf. Ein Termin in der Zukunft plant den Artikel.
                </p>
                <InputError message={errors.published_at} />
            </div>
        </CardContent>
    </Card>
</div>
