import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import AddressForm from '@/components/AddressForm.svelte';
import type { AddressData } from '@/types/cart';

function address(overrides: Partial<AddressData> = {}): AddressData {
    return {
        company: '',
        salutation: '',
        first_name: '',
        last_name: '',
        street: '',
        house_number: '',
        address_line2: '',
        zip: '',
        city: '',
        country: 'DE',
        phone: '',
        ...overrides,
    };
}

describe('AddressForm', () => {
    it('renders the legend and pre-fills known values', () => {
        render(AddressForm, {
            props: {
                data: address({ first_name: 'Max', last_name: 'Mustermann' }),
                prefix: 'shipping',
                legend: 'Lieferadresse',
            },
        });

        expect(screen.getByText('Lieferadresse')).toBeInTheDocument();
        expect(screen.getByLabelText(/Vorname/)).toHaveValue('Max');
        expect(screen.getByLabelText(/Nachname/)).toHaveValue('Mustermann');
    });

    it('calls onUpdate with prefix/key/value when a field changes', async () => {
        const onUpdate = vi.fn();

        render(AddressForm, {
            props: {
                data: address(),
                prefix: 'shipping',
                legend: 'Lieferadresse',
                onUpdate,
            },
        });

        await fireEvent.input(screen.getByLabelText(/Straße/), {
            target: { value: 'Musterstraße' },
        });

        expect(onUpdate).toHaveBeenCalledWith({
            prefix: 'shipping',
            key: 'street',
            value: 'Musterstraße',
        });
    });

    it('shows a field-level error message', () => {
        render(AddressForm, {
            props: {
                data: address(),
                prefix: 'shipping',
                legend: 'Lieferadresse',
                errors: { 'shipping.first_name': 'Pflichtfeld.' },
            },
        });

        expect(screen.getByText('Pflichtfeld.')).toBeInTheDocument();
    });

    it('only shows the company field when the salutation is "Firma"', () => {
        const { rerender } = render(AddressForm, {
            props: {
                data: address({ salutation: 'Herr' }),
                prefix: 'shipping',
                legend: 'Lieferadresse',
            },
        });

        expect(screen.queryByLabelText('Firma')).not.toBeInTheDocument();

        rerender({
            data: address({ salutation: 'Firma' }),
            prefix: 'shipping',
            legend: 'Lieferadresse',
        });

        expect(screen.getByLabelText('Firma')).toBeInTheDocument();
    });
});
