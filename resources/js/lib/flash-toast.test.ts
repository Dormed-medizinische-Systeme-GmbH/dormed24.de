import { beforeEach, describe, expect, it, vi } from 'vitest';

const onMock = vi.fn();

vi.mock('@inertiajs/svelte', () => ({
    router: { on: onMock },
}));

const toastMock = {
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
};

vi.mock('svelte-sonner', () => ({ toast: toastMock }));

const { initializeFlashToast } = await import('@/lib/flash-toast');

function flashHandler(): (event: unknown) => void {
    const [, handler] = onMock.mock.calls.at(-1) as [string, () => void];

    return handler;
}

describe('initializeFlashToast', () => {
    beforeEach(() => {
        onMock.mockClear();
        toastMock.success.mockClear();
        toastMock.error.mockClear();
        toastMock.info.mockClear();
        toastMock.warning.mockClear();
    });

    it('registers a "flash" listener on the Inertia router', () => {
        initializeFlashToast();

        expect(onMock).toHaveBeenCalledWith('flash', expect.any(Function));
    });

    it('shows a toast of the matching type with the flash message', () => {
        initializeFlashToast();

        flashHandler()({
            detail: {
                flash: { toast: { type: 'success', message: 'Gespeichert.' } },
            },
        });

        expect(toastMock.success).toHaveBeenCalledWith('Gespeichert.');
    });

    it('routes error-type flash toasts to toast.error', () => {
        initializeFlashToast();

        flashHandler()({
            detail: {
                flash: {
                    toast: { type: 'error', message: 'Fehlgeschlagen.' },
                },
            },
        });

        expect(toastMock.error).toHaveBeenCalledWith('Fehlgeschlagen.');
    });

    it('does nothing when there is no flash toast payload', () => {
        initializeFlashToast();

        flashHandler()({ detail: {} });

        expect(toastMock.success).not.toHaveBeenCalled();
        expect(toastMock.error).not.toHaveBeenCalled();
    });

    it('does nothing when the event has no detail at all', () => {
        initializeFlashToast();

        flashHandler()({});

        expect(toastMock.success).not.toHaveBeenCalled();
    });
});
