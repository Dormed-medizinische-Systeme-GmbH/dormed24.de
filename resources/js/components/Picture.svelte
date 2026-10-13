<script lang="ts">
    import { avifSrc } from '@/lib/picture';

    // `src` ist die WebP-URL; AVIF liegt daneben (siehe lib/picture.ts).
    // `class` landet auf dem <img>, nicht auf <picture>.
    let {
        src,
        alt,
        width,
        height,
        loading = 'lazy',
        fetchpriority,
        sizes,
        draggable,
        class: className,
    }: {
        src: string;
        alt: string;
        width?: number | null;
        height?: number | null;
        loading?: 'lazy' | 'eager';
        fetchpriority?: 'high' | 'low' | 'auto';
        sizes?: string;
        draggable?: boolean;
        class?: string;
    } = $props();

    const avif = $derived(avifSrc(src));
</script>

<!-- `contents`: <picture> erzeugt keine eigene Box, damit size-full & Co. am <img> weiter gegen den Eltern-Container rechnen. -->
<picture class="contents">
    {#if avif}
        <source type="image/avif" srcset={avif} {sizes} />
        <source type="image/webp" srcset={src} {sizes} />
    {/if}
    <img
        {src}
        {alt}
        width={width ?? undefined}
        height={height ?? undefined}
        {loading}
        {fetchpriority}
        decoding="async"
        {draggable}
        class={className}
    />
</picture>
