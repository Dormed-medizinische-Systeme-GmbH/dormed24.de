import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/svelte';
import { afterEach } from 'vitest';

// jsdom kennt die Web Animations API nicht. Svelte-Transitions rufen beim
// Ein- und Ausblenden element.animate() auf und warten beim Ausblenden auf
// das "finish"-Event, bevor das Element aus dem DOM fliegt.
// https://github.com/testing-library/svelte-testing-library/issues/206
if (!Element.prototype.animate) {
    Element.prototype.animate = (() => {
        const listeners: Record<string, (() => void)[]> = {};
        const animation = {
            finished: Promise.resolve(),
            cancel: () => {},
            finish: () => listeners.finish?.forEach((callback) => callback()),
            play: () => {},
            pause: () => {},
            addEventListener: (type: string, callback: () => void) => {
                (listeners[type] ??= []).push(callback);
            },
            removeEventListener: () => {},
        };

        queueMicrotask(() => animation.finish());

        return animation;
    }) as unknown as typeof Element.prototype.animate;
}

afterEach(() => {
    cleanup();
});
