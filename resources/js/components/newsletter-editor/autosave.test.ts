import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createAutosave } from '@/components/newsletter-editor/autosave';
import type { AutosaveState } from '@/components/newsletter-editor/autosave';

describe('createAutosave', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('saves once after the delay, no matter how many changes came in', async () => {
        const save = vi.fn().mockResolvedValue(true);
        const autosave = createAutosave(save, { delay: 3000 });

        autosave.touch();
        await vi.advanceTimersByTimeAsync(2000);
        autosave.touch();
        await vi.advanceTimersByTimeAsync(2999);
        expect(save).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(1);
        expect(save).toHaveBeenCalledTimes(1);
    });

    it('reports dirty, saving and the result', async () => {
        const states: AutosaveState[] = [];
        const autosave = createAutosave(() => Promise.resolve(false), {
            delay: 10,
            onStateChange: (state) => states.push(state),
        });

        autosave.touch();
        await vi.advanceTimersByTimeAsync(10);

        expect(states).toEqual(['dirty', 'saving', 'error']);
    });

    it('flush saves immediately and cancels the pending timer', async () => {
        const save = vi.fn().mockResolvedValue(true);
        const autosave = createAutosave(save, { delay: 3000 });

        autosave.touch();
        await expect(autosave.flush()).resolves.toBe(true);
        await vi.advanceTimersByTimeAsync(5000);

        expect(save).toHaveBeenCalledTimes(1);
    });

    it('never saves twice at the same time and saves again after a change during saving', async () => {
        let finish!: (ok: boolean) => void;
        const save = vi
            .fn()
            .mockImplementationOnce(
                () => new Promise<boolean>((resolve) => (finish = resolve)),
            )
            .mockResolvedValue(true);
        const autosave = createAutosave(save);

        const first = autosave.flush();
        const second = autosave.flush();
        expect(save).toHaveBeenCalledTimes(1);

        finish(true);
        await first;
        await second;

        expect(save).toHaveBeenCalledTimes(2);
    });

    it('treats a throwing save as failed', async () => {
        const autosave = createAutosave(() =>
            Promise.reject(new Error('offline')),
        );

        await expect(autosave.flush()).resolves.toBe(false);
    });

    it('cancel drops a pending save', async () => {
        const save = vi.fn().mockResolvedValue(true);
        const autosave = createAutosave(save, { delay: 100 });

        autosave.touch();
        autosave.cancel();
        await vi.advanceTimersByTimeAsync(1000);

        expect(save).not.toHaveBeenCalled();
    });
});
