<script module lang="ts">
    export const layout = {
        breadcrumbs: [{ title: 'Newsletter', href: '/admin/newsletters' }],
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Mail from '@lucide/svelte/icons/mail';
    import Plus from '@lucide/svelte/icons/plus';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import * as NewsletterController from '@/actions/App/Http/Controllers/Admin/NewsletterController';
    import AppHead from '@/components/AppHead.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import * as Table from '@/components/ui/table';

    type NewsletterRow = {
        id: number;
        name: string;
        subject: string;
        status: string;
        updated_at: string;
    };

    let { newsletters }: { newsletters: NewsletterRow[] } = $props();

    const statusLabels: Record<string, string> = {
        draft: 'Entwurf',
        sent: 'Verschickt',
    };

    let creating = $state(false);

    function create() {
        creating = true;
        router.post(
            NewsletterController.store.url(),
            {},
            { onFinish: () => (creating = false) },
        );
    }

    function destroy(newsletter: NewsletterRow) {
        if (!confirm(`„${newsletter.name}“ wirklich löschen?`)) {
            return;
        }

        router.delete(NewsletterController.destroy.url(newsletter.id), {
            preserveScroll: true,
        });
    }
</script>

<AppHead title="Newsletter" />

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h1 class="text-xl font-semibold">Newsletter</h1>
            <p class="text-sm text-muted-foreground">
                Entwürfe im Editor bauen und als Testmail an die Shop-Adresse
                schicken.
            </p>
        </div>
        <Button onclick={create} disabled={creating}>
            <Plus class="size-4" />
            Neuer Newsletter
        </Button>
    </div>

    {#if newsletters.length === 0}
        <div
            class="flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-center text-muted-foreground"
        >
            <Mail class="size-8" />
            <p>Noch kein Newsletter angelegt.</p>
        </div>
    {:else}
        <div class="rounded-xl border">
            <Table.Root>
                <Table.Header>
                    <Table.Row>
                        <Table.Head>Name</Table.Head>
                        <Table.Head>Betreff</Table.Head>
                        <Table.Head>Status</Table.Head>
                        <Table.Head>Geändert</Table.Head>
                        <Table.Head class="w-12"></Table.Head>
                    </Table.Row>
                </Table.Header>
                <Table.Body>
                    {#each newsletters as newsletter (newsletter.id)}
                        <Table.Row>
                            <Table.Cell class="font-medium">
                                <Link
                                    href={NewsletterController.edit.url(
                                        newsletter.id,
                                    )}
                                    class="hover:underline"
                                >
                                    {newsletter.name}
                                </Link>
                            </Table.Cell>
                            <Table.Cell>{newsletter.subject}</Table.Cell>
                            <Table.Cell>
                                <Badge variant="secondary">
                                    {statusLabels[newsletter.status] ??
                                        newsletter.status}
                                </Badge>
                            </Table.Cell>
                            <Table.Cell class="text-muted-foreground">
                                {newsletter.updated_at}
                            </Table.Cell>
                            <Table.Cell>
                                {#if newsletter.status === 'draft'}
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`${newsletter.name} löschen`}
                                        onclick={() => destroy(newsletter)}
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                {/if}
                            </Table.Cell>
                        </Table.Row>
                    {/each}
                </Table.Body>
            </Table.Root>
        </div>
    {/if}
</div>
