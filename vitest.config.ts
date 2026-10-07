import { fileURLToPath } from 'node:url';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import { defineConfig } from 'vitest/config';

/**
 * Eigene Konfiguration statt vite.config.ts: Laravel- und Wayfinder-Plugin
 * gehören zum App-Build und würden in Tests PHP-Befehle starten.
 * Die Wayfinder-Dateien (@/actions, @/routes) müssen vorher generiert sein,
 * lokal durch `npm run dev`/`build`, in der CI durch den Build-Schritt.
 */
export default defineConfig({
    plugins: [svelte()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
        // Svelte 5 hat getrennte Client- und Server-Builds; ohne 'browser'
        // landen Komponenten-Tests im SSR-Build und scheitern an DOM-APIs.
        // https://svelte.dev/docs/svelte/testing
        conditions: process.env.VITEST ? ['browser'] : undefined,
    },
    test: {
        environment: 'jsdom',
        // Svelte-Transitions (fly, fade) laufen über requestAnimationFrame,
        // das jsdom nur für "sichtbare" Dokumente bereitstellt.
        environmentOptions: {
            jsdom: { pretendToBeVisual: true },
        },
        setupFiles: ['./resources/js/testing/setup.ts'],
        include: ['resources/js/**/*.test.ts'],
    },
});
