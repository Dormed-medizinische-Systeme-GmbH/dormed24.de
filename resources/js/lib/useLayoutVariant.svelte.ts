import { page, router } from '@inertiajs/svelte';
import { resolveLayoutVariant, withLayoutVariant } from '@/lib/layoutVariants';
import type { LayoutVariantOption } from '@/lib/layoutVariants';

/**
 * Hält die gewählte Layout-Variante einer Seite (Wert aus `?variant=`) und
 * schreibt Änderungen per `history.replaceState` zurück in die URL, ohne
 * Inertia-Navigation (kein Unsaved-Changes-Guard, kein Reload).
 *
 * Beim Initialisieren einer Komponente aufrufen.
 */
export function useLayoutVariant<K extends string>(
    options: readonly LayoutVariantOption<K>[],
) {
    let current = $state<K>(resolveLayoutVariant(page.url, options));

    function writeToUrl(key: K) {
        const target = withLayoutVariant(window.location.href, key, options);

        if (target !== window.location.pathname + window.location.search) {
            window.history.replaceState(window.history.state, '', target);
        }
    }

    // Inertia-Antworten (Upload, Reload …) setzen die URL zurück: Parameter erneuern.
    $effect(() => {
        const key = current;

        return router.on('navigate', () => writeToUrl(key));
    });

    return {
        options,
        get current(): K {
            return current;
        },
        select(key: string) {
            current = resolveLayoutVariant(`?variant=${key}`, options);
            writeToUrl(current);
        },
    };
}
