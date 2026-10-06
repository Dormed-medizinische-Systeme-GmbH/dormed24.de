const formatter = new Intl.NumberFormat('de-DE', {
    style: 'currency',
    currency: 'EUR',
});

export function formatPrice(value: number | string): string {
    return formatter.format(Number(value));
}
