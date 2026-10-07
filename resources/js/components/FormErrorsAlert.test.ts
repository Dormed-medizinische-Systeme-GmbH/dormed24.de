import { render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import FormErrorsAlert from '@/components/FormErrorsAlert.svelte';

describe('FormErrorsAlert', () => {
    it('renders nothing when there are no errors', () => {
        render(FormErrorsAlert, { props: { errors: {} } });

        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });

    it('shows the title and error details when errors are present', () => {
        render(FormErrorsAlert, {
            props: {
                errors: { email: 'E-Mail ist ungültig.' },
                title: 'Registrierung fehlgeschlagen.',
            },
        });

        expect(
            screen.getByText('Registrierung fehlgeschlagen.'),
        ).toBeInTheDocument();
        expect(screen.getByText('E-Mail ist ungültig.')).toBeInTheDocument();
    });

    it('ignores undefined error values', () => {
        render(FormErrorsAlert, {
            props: { errors: { email: undefined, name: undefined } },
        });

        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });

    it('hides error details when showDetails is false', () => {
        render(FormErrorsAlert, {
            props: {
                errors: { email: 'E-Mail ist ungültig.' },
                showDetails: false,
            },
        });

        expect(screen.getByRole('alert')).toBeInTheDocument();
        expect(
            screen.queryByText('E-Mail ist ungültig.'),
        ).not.toBeInTheDocument();
    });

    it('schedules the auto-dismiss timeout with the configured delay', () => {
        // Asserts the $effect schedules the right timer instead of waiting
        // for the actual DOM removal: that depends on svelte/transition's
        // "fly" outro completing, which runs on a real rAF loop jsdom
        // doesn't reliably drive under test.
        const setTimeoutSpy = vi.spyOn(globalThis, 'setTimeout');

        render(FormErrorsAlert, {
            props: {
                errors: { email: 'E-Mail ist ungültig.' },
                autoDismissMs: 4000,
            },
        });

        expect(setTimeoutSpy).toHaveBeenCalledWith(expect.any(Function), 4000);
    });
});
