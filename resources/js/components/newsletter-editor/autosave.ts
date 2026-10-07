/**
 * Speichert verzögert, sobald sich im Editor etwas ändert, und nie zwei
 * Speichervorgänge gleichzeitig. Kommt während eines Speicherns eine
 * Änderung, wird danach noch einmal gespeichert.
 */
export type AutosaveState = 'idle' | 'dirty' | 'saving' | 'saved' | 'error';

export function createAutosave(
    save: () => Promise<boolean>,
    {
        delay = 3000,
        onStateChange = () => {},
    }: { delay?: number; onStateChange?: (state: AutosaveState) => void } = {},
) {
    let timer: ReturnType<typeof setTimeout> | null = null;
    let running: Promise<boolean> | null = null;
    let pendingAfterRun = false;

    async function run(): Promise<boolean> {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }

        if (running) {
            pendingAfterRun = true;

            return running;
        }

        onStateChange('saving');
        running = save()
            .catch(() => false)
            .then((ok) => {
                running = null;
                onStateChange(ok ? 'saved' : 'error');

                if (pendingAfterRun) {
                    pendingAfterRun = false;

                    return run();
                }

                return ok;
            });

        return running;
    }

    return {
        /** Eine Änderung ist passiert: nach `delay` speichern. */
        touch() {
            onStateChange('dirty');

            if (timer) {
                clearTimeout(timer);
            }

            timer = setTimeout(() => void run(), delay);
        },
        /** Sofort speichern (Button, Strg+S, vor der Testmail). */
        flush: run,
        cancel() {
            if (timer) {
                clearTimeout(timer);
                timer = null;
            }
        },
    };
}
