<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import LogOut from '@lucide/svelte/icons/log-out';
    import MapPin from '@lucide/svelte/icons/map-pin';
    import Package from '@lucide/svelte/icons/package';
    import Settings from '@lucide/svelte/icons/settings';
    import Shield from '@lucide/svelte/icons/shield';
    import type { Snippet } from 'svelte';
    import Heading from '@/components/Heading.svelte';
    import { Button } from '@/components/ui/button';
    import { Separator } from '@/components/ui/separator';
    import { currentUrlState } from '@/lib/currentUrl.svelte';
    import { toUrl } from '@/lib/utils';
    import { logout } from '@/routes';
    import customerRoutes from '@/routes/customer';
    import { edit as editProfile } from '@/routes/profile';
    import { edit as editSecurity } from '@/routes/security';
    import type { NavItem } from '@/types';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    const sidebarNavItems: NavItem[] = [
        {
            title: 'Bestellungen',
            href: customerRoutes.orders(),
            icon: Package,
        },
        {
            title: 'Adressen',
            href: '/settings/addresses',
            icon: MapPin,
        },
        {
            title: 'Profil',
            href: editProfile(),
            icon: Settings,
        },
        {
            title: 'Sicherheit',
            href: editSecurity(),
            icon: Shield,
        },
    ];

    const url = currentUrlState();
</script>

<div class="mx-auto max-w-7xl px-4 py-6 lg:px-8">
    <Heading
        title="Einstellungen"
        description="Profil und Kontoeinstellungen verwalten"
    />

    <div class="flex flex-col lg:flex-row lg:space-x-12">
        <aside class="w-full max-w-xl lg:w-48">
            <nav
                class="flex flex-col space-y-1 space-x-0"
                aria-label="Settings"
            >
                {#each sidebarNavItems as item (toUrl(item.href))}
                    <Button
                        variant="ghost"
                        class="w-full justify-start {url.isCurrentUrl(
                            item.href,
                            url.currentUrl,
                        )
                            ? 'bg-muted'
                            : ''}"
                        asChild
                    >
                        {#snippet children(props)}
                            <Link href={toUrl(item.href)} class={props.class}>
                                <item.icon class="mr-2 size-4" />
                                {item.title}
                            </Link>
                        {/snippet}
                    </Button>
                {/each}

                <Separator class="my-2" />

                <Button
                    variant="ghost"
                    class="w-full justify-start text-muted-foreground hover:text-destructive"
                    asChild
                >
                    {#snippet children(props)}
                        <Link
                            href={logout()}
                            as="button"
                            class={props.class}
                            onclick={() => router.flushAll()}
                        >
                            <LogOut class="mr-2 size-4" />
                            Abmelden
                        </Link>
                    {/snippet}
                </Button>
            </nav>
        </aside>

        <Separator class="my-6 lg:hidden" />

        <div class="min-w-0 flex-1 md:max-w-5xl">
            <section class="space-y-12">
                {@render children?.()}
            </section>
        </div>
    </div>
</div>
