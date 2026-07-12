<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import { Link } from '@inertiajs/svelte';
    import AlertCircle from '@lucide/svelte/icons/circle-alert';
    import AppFooter from '@/components/AppFooter.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ShopHeader from '@/components/ShopHeader.svelte';
    import { Button } from '@/components/ui/button';

    type ContactInfo = {
        email: string;
        phone: string;
        fax: string;
        phone_href: string;
        fax_href: string;
    };

    const contact = $derived(page.props.contact as ContactInfo);
</script>

<AppHead title="Fehler bei der Bestellung" />

<div class="flex min-h-screen flex-col bg-muted">
    <ShopHeader />

    <main class="flex-1 mx-auto max-w-2xl px-4 py-16 text-center lg:px-8">
        <div class="mb-5 flex justify-center">
            <div
                class="flex size-16 items-center justify-center rounded-full bg-destructive/10"
            >
                <AlertCircle class="size-8 text-destructive" />
            </div>
        </div>
        <h1 class="text-2xl font-bold text-foreground">
            Bei Ihrer Bestellung ist ein Fehler aufgetreten.
        </h1>
        <p class="mt-3 text-sm text-muted-foreground">
            Bitte versuchen Sie es erneut oder kontaktieren Sie uns unter
            <a
                href={contact.phone_href}
                class="text-brand-accent hover:underline">{contact.phone}</a
            >.
        </p>
        <div class="mt-8 flex justify-center gap-3">
            <Button asChild variant="outline">
                {#snippet children(props)}
                    <Link href="/checkout/confirm" class={props.class}
                        >Zurück zur Bestellung</Link
                    >
                {/snippet}
            </Button>
            <Button asChild class="bg-primary text-white hover:bg-primary/90">
                {#snippet children(props)}
                    <Link href="/" class={props.class}>Zur Startseite</Link>
                {/snippet}
            </Button>
        </div>
    </main>

    <AppFooter />
</div>
