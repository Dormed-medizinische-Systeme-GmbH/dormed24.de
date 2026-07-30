<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'Kategorien', href: '/admin/categories' },
            { title: 'Neue Kategorie' },
        ],
    };
</script>

<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import * as AdminCategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';

    const form = useForm({
        name: '',
        slug: '',
        description: '',
    });

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.post(AdminCategoryController.store.url());
    }

    function slugify(value: string): string {
        return value
            .toLowerCase()
            .replace(
                /[äöüß]/g,
                (c) => ({ ä: 'ae', ö: 'oe', ü: 'ue', ß: 'ss' })[c] ?? c,
            )
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    }
</script>

<AppHead title="Neue Kategorie — Admin" />

<div class="flex h-full flex-1 flex-col gap-6 p-4">
    <h1 class="text-xl font-semibold">Neue Kategorie</h1>

    <form onsubmit={submit} class="flex max-w-2xl flex-col gap-6">
        <div class="flex flex-col gap-2">
            <Label for="name">Name</Label>
            <Input
                id="name"
                bind:value={form.name}
                placeholder="Kategoriename"
                oninput={() => {
                    if (
                        !form.slug ||
                        form.slug === slugify(form.name.slice(0, -1))
                    ) {
                        form.slug = slugify(form.name);
                    }
                }}
            />
            {#if form.errors.name}
                <p class="text-sm text-destructive">{form.errors.name}</p>
            {/if}
        </div>

        <div class="flex flex-col gap-2">
            <Label for="slug">Slug</Label>
            <Input
                id="slug"
                bind:value={form.slug}
                placeholder="kategorie-slug"
            />
            {#if form.errors.slug}
                <p class="text-sm text-destructive">{form.errors.slug}</p>
            {/if}
        </div>

        <div class="flex flex-col gap-2">
            <Label for="description">Beschreibung</Label>
            <textarea
                id="description"
                bind:value={form.description}
                rows={4}
                placeholder="Beschreibung…"
                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
            ></textarea>
            {#if form.errors.description}
                <p class="text-sm text-destructive">
                    {form.errors.description}
                </p>
            {/if}
        </div>

        <div class="flex gap-3">
            <Button type="submit" disabled={form.processing}>
                {form.processing ? 'Erstellt…' : 'Erstellen'}
            </Button>
            <Button
                type="button"
                variant="outline"
                onclick={() => history.back()}
            >
                Abbrechen
            </Button>
        </div>
    </form>
</div>
