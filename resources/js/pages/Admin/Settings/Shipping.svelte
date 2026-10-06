<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'Einstellungen', href: '/admin/settings' },
            { title: 'Versandarten', href: '/admin/settings/shipping' },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { Plus, Pencil, Trash2, Check, X } from '@lucide/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { guardUnsavedChanges } from '@/lib/unsavedChanges';

    type ShippingMethod = {
        id: number;
        name: string;
        description: string | null;
        price: string | null;
        sort_order: number;
    };

    let { methods }: { methods: ShippingMethod[] } = $props();

    let editingId = $state<number | null>(null);
    let editName = $state('');
    let editDescription = $state('');
    let editPrice = $state('');

    let addingNew = $state(false);
    let newName = $state('');
    let newDescription = $state('');
    let newPrice = $state('');

    // Ungespeichert = eine Zeile in Bearbeitung weicht vom Stand ab oder im
    // „Neu“-Formular steht schon etwas.
    const isDirty = $derived.by(() => {
        const editing = methods.find((method) => method.id === editingId);

        const editChanged =
            editing !== undefined &&
            (editName !== editing.name ||
                editDescription !== (editing.description ?? '') ||
                editPrice !== (editing.price ?? ''));

        const newStarted = addingNew && (newName !== '' || newDescription !== '' || newPrice !== '');

        return editChanged || newStarted;
    });

    $effect(() => guardUnsavedChanges(() => isDirty));

    function startEdit(method: ShippingMethod) {
        editingId = method.id;
        editName = method.name;
        editDescription = method.description ?? '';
        editPrice = method.price ?? '';
    }

    function cancelEdit() {
        editingId = null;
        editName = '';
        editDescription = '';
        editPrice = '';
    }

    function saveEdit(id: number) {
        router.put(`/admin/settings/shipping/${id}`, {
            name: editName,
            description: editDescription === '' ? null : editDescription,
            price: editPrice === '' ? null : editPrice,
        }, {
            onSuccess: () => cancelEdit(),
        });
    }

    function deleteMethod(id: number) {
        if (!confirm('Versandart wirklich löschen?')) {
            return;
        }

        router.delete(`/admin/settings/shipping/${id}`);
    }

    function saveNew() {
        router.post('/admin/settings/shipping', {
            name: newName,
            description: newDescription === '' ? null : newDescription,
            price: newPrice === '' ? null : newPrice,
        }, {
            onSuccess: () => {
                addingNew = false;
                newName = '';
                newDescription = '';
                newPrice = '';
            },
        });
    }

    function cancelNew() {
        addingNew = false;
        newName = '';
        newDescription = '';
        newPrice = '';
    }

    function formatPrice(price: string | null): string {
        if (price === null) {
return 'auf Anfrage';
}

        return parseFloat(price).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' });
    }
</script>

<AppHead title="Versandarten — Einstellungen — Admin" />

<div class="flex h-full flex-1 flex-col gap-6 p-4 max-w-2xl">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Versandarten</h1>
        <Button
            size="sm"
            onclick={() => {
 addingNew = true; 
}}
            disabled={addingNew}
        >
            <Plus class="size-4" />
            Hinzufügen
        </Button>
    </div>

    <div class="rounded-lg border">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b bg-muted/50">
                    <th class="px-4 py-3 text-left font-medium text-muted-foreground">Bezeichnung</th>
                    <th class="px-4 py-3 text-left font-medium text-muted-foreground">Beschreibung</th>
                    <th class="px-4 py-3 text-left font-medium text-muted-foreground w-36">Preis</th>
                    <th class="px-4 py-3 w-24"></th>
                </tr>
            </thead>
            <tbody>
                {#each methods as method (method.id)}
                    <tr class="border-b last:border-0 hover:bg-muted/30">
                        {#if editingId === method.id}
                            <td class="px-4 py-2">
                                <Input bind:value={editName} class="h-8" placeholder="Bezeichnung" autofocus />
                            </td>
                            <td class="px-4 py-2">
                                <Input bind:value={editDescription} class="h-8" placeholder="Kurze Beschreibung (optional)" />
                            </td>
                            <td class="px-4 py-2">
                                <Input type="number" step="0.01" min="0" bind:value={editPrice} class="h-8" placeholder="auf Anfrage" />
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex gap-1">
                                    <Button size="icon" class="size-8" onclick={() => saveEdit(method.id)}>
                                        <Check class="size-4" />
                                    </Button>
                                    <Button size="icon" variant="ghost" class="size-8" onclick={cancelEdit}>
                                        <X class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        {:else}
                            <td class="px-4 py-3">{method.name}</td>
                            <td class="px-4 py-3 text-sm text-muted-foreground">{method.description ?? '—'}</td>
                            <td class="px-4 py-3 tabular-nums">{formatPrice(method.price)}</td>
                            <td class="px-4 py-3">
                                <div class="flex gap-1 justify-end">
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        class="size-8"
                                        onclick={() => startEdit(method)}
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        class="size-8 text-destructive hover:text-destructive"
                                        onclick={() => deleteMethod(method.id)}
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        {/if}
                    </tr>
                {/each}

                {#if addingNew}
                    <tr class="border-b last:border-0 bg-muted/20">
                        <td class="px-4 py-2">
                            <Input bind:value={newName} class="h-8" placeholder="z.B. Standardversand (DPD)" autofocus />
                        </td>
                        <td class="px-4 py-2">
                            <Input bind:value={newDescription} class="h-8" placeholder="Kurze Beschreibung (optional)" />
                        </td>
                        <td class="px-4 py-2">
                            <Input type="number" step="0.01" min="0" bind:value={newPrice} class="h-8" placeholder="auf Anfrage" />
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex gap-1">
                                <Button size="icon" class="size-8" onclick={saveNew} disabled={!newName}>
                                    <Check class="size-4" />
                                </Button>
                                <Button size="icon" variant="ghost" class="size-8" onclick={cancelNew}>
                                    <X class="size-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                {/if}

                {#if methods.length === 0 && !addingNew}
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-muted-foreground">
                            Noch keine Versandarten angelegt.
                        </td>
                    </tr>
                {/if}
            </tbody>
        </table>
    </div>

    <p class="text-xs text-muted-foreground">
        Leer gelassener Preis erscheint im Shop als „auf Anfrage".
    </p>
</div>
