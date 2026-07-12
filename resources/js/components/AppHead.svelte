<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';

    let {
        title = '',
        description = null,
        canonical = null,
        ogImage = null,
        ogType = 'website',
        children,
    }: {
        title?: string;
        description?: string | null;
        /** Pfad (oder absolute URL) der kanonischen Version dieser Seite — ohne Query-Parameter übergeben. */
        canonical?: string | null;
        ogImage?: string | null;
        ogType?: string;
        children?: Snippet;
    } = $props();

    const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
    const fullTitle = $derived(title ? `${title} - ${appName}` : appName);
    const metaDescription = $derived(description ?? null);
    const ogTitle = $derived(fullTitle);
    const canonicalUrl = $derived.by(() => {
        if (!canonical) {
            return null;
        }

        // APP_URL als kanonische Basis (auch clientseitig — normalisiert
        // z. B. www vs. non-www); og:url verlangt absolute URLs, und im SSR
        // gibt es kein window.location (V4 SEO-1).
        const base =
            page.props.appUrl ||
            (typeof window !== 'undefined' ? window.location.origin : null);

        return base ? new URL(canonical, base).href : canonical;
    });
</script>

<svelte:head>
    <title>{fullTitle}</title>

    {#if metaDescription}
        <meta name="description" content={metaDescription} />
    {/if}

    {#if canonicalUrl}
        <link rel="canonical" href={canonicalUrl} />
    {/if}

    <!-- Open Graph -->
    <meta property="og:type" content={ogType} />
    {#if canonicalUrl}
        <meta property="og:url" content={canonicalUrl} />
    {/if}
    <meta property="og:title" content={ogTitle} />
    {#if metaDescription}
        <meta property="og:description" content={metaDescription} />
    {/if}
    {#if ogImage}
        <meta property="og:image" content={ogImage} />
    {/if}

    <!-- Twitter Card -->
    <meta
        name="twitter:card"
        content={ogImage ? 'summary_large_image' : 'summary'}
    />
    <meta name="twitter:title" content={ogTitle} />
    {#if metaDescription}
        <meta name="twitter:description" content={metaDescription} />
    {/if}
    {#if ogImage}
        <meta name="twitter:image" content={ogImage} />
    {/if}

    {@render children?.()}
</svelte:head>
