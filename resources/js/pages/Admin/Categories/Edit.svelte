<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'Kategorien', href: '/admin/categories' },
            { title: 'Bearbeiten' },
        ],
    };
</script>

<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import * as AdminCategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type Category = {
        id: number;
        name: string;
        slug: string;
        description: string | null;
    };

    let { category }: { category: Category } = $props();

    // svelte-ignore state_referenced_locally
    const form = useForm({
        name: category.name,
        slug: category.slug,
        description: category.description ?? '',
    });

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.put(AdminCategoryController.update.url(category.id));
    }
</script>

<AppHead title="{category.name} bearbeiten — Admin" />

<div class="flex h-full flex-1 flex-col gap-6 p-4">
    <h1 class="text-xl font-semibold">Kategorie bearbeiten</h1>

    <form onsubmit={submit} class="flex max-w-2xl flex-col gap-6">
        <div class="flex flex-col gap-2">
            <Label for="name">Name</Label>
            <Input
                id="name"
                bind:value={form.name}
                placeholder="Kategoriename"
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
                {form.processing ? 'Speichert…' : 'Speichern'}
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
