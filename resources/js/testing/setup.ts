import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/svelte';
import { afterEach } from 'vitest';

// jsdom doesn't implement the Web Animations API; Svelte's transitions
// (fly, fade, ...) call element.animate() during mount/unmount, and the
// outro waits for a "finish" event before removing the element from the
// DOM. https://github.com/testing-library/svelte-testing-library/issues/206
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
