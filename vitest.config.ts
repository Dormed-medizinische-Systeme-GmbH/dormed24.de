import { svelte } from '@sveltejs/vite-plugin-svelte';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [svelte()],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
        // Svelte 5 ships separate client/server exports; without this,
        // component mounts in tests resolve the SSR build and crash on DOM
        // APIs. https://svelte.dev/docs/svelte/testing
        conditions: process.env.VITEST ? ['browser'] : undefined,
    },
    test: {
        environment: 'jsdom',
        // Off by default in Vitest (CSS is mocked for speed) - needed here
        // because CookieBanner.svelte's visibility is asserted via its
        // actual compiled <style> rules (display: none/block), not inline
        // styles or classes alone.
        css: true,
        // Svelte transitions (fly, fade, ...) drive their outro via
        // requestAnimationFrame, which jsdom only implements when the
        // document is "visual" - without this, unmount-on-dismiss tests
        // hang until timeout.
        environmentOptions: {
            jsdom: { pretendToBeVisual: true },
        },
        setupFiles: ['./resources/js/testing/setup.ts'],
        include: ['resources/js/**/*.{test,spec}.ts'],
    },
});
