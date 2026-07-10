import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

const isSvelteCheck = process.argv.some((argument) =>
    argument.includes('svelte-check'),
);

if (isSvelteCheck) {
    process.env.LARAVEL_BYPASS_ENV_CHECK ??= '1';
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/app.css', 'resources/js/app.ts'],
            // SSR-Eingang: dieselbe app.ts wie fürs Client-Rendering. Der
            // @inertiajs/vite-Plugin wrappt sie beim `vite build --ssr`
            // automatisch mit Server-Bootstrap. Ohne diese Zeile würde das
            // Laravel-Plugin die komplette input-Liste (inkl. app.css) als
            // SSR-Entry nehmen. Ausgabe -> bootstrap/ssr/.
            ssr: 'resources/js/app.ts',
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        svelte(),
        wayfinder({
            formVariants: true,
        }),
    ],
    server: {
        watch: {
            ignored: [
                '**/database/*.sqlite',
                '**/database/*.sqlite-*',
                '**/storage/framework/**',
                '**/vendor/**',
            ],
        },
    },
});
