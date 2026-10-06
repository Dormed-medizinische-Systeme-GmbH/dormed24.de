import { router } from '@inertiajs/svelte';

const MESSAGE = 'Es gibt ungespeicherte Änderungen. Seite trotzdem verlassen?';

/**
 * Fragt vor dem Verlassen der Seite nach, solange `isDirty()` true ist:
 * bei Inertia-Navigationen (Links, Sidebar, Breadcrumb, router.visit) und
 * beim Neuladen/Schließen des Tabs (beforeunload, Browser-Standarddialog).
 *
 * Nur GET-Visits werden abgefangen: Das Speichern selbst (PUT/POST) und
 * Aktionen auf derselben Seite sollen nicht nachfragen. Prefetches laufen
 * ohne Rückfrage durch.
 *
 * In einem `$effect` aufrufen; die zurückgegebene Funktion räumt auf.
 */
export function guardUnsavedChanges(isDirty: () => boolean): () => void {
    const removeInertiaGuard = router.on('before', (event) => {
        const visit = event.detail.visit;

        if (visit.prefetch || visit.method !== 'get' || !isDirty()) {
            return;
        }

        if (!window.confirm(MESSAGE)) {
            event.preventDefault();
        }
    });

    const onBeforeUnload = (event: BeforeUnloadEvent) => {
        if (isDirty()) {
            event.preventDefault();
        }
    };

    window.addEventListener('beforeunload', onBeforeUnload);

    return () => {
        removeInertiaGuard();
        window.removeEventListener('beforeunload', onBeforeUnload);
    };
}
