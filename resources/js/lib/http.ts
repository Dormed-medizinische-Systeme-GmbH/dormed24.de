/**
 * Fetch helper for JSON endpoints that bypass the Inertia router (PayPal SDK
 * callbacks, admin connection checks). Laravel's CSRF middleware rejects
 * requests without the XSRF token (419), so this always sends it from the
 * cookie — the same mechanism the Inertia XHR client uses.
 */
export async function fetchJson<T = Record<string, unknown>>(
    url: string,
    options: {
        method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
        body?: unknown;
    } = {},
): Promise<{ ok: boolean; status: number; data: T }> {
    const response = await fetch(url, {
        method: options.method ?? 'GET',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
            ...(options.body !== undefined
                ? { 'Content-Type': 'application/json' }
                : {}),
        },
        body:
            options.body !== undefined
                ? JSON.stringify(options.body)
                : undefined,
    });

    const data = (await response.json().catch(() => ({}))) as T;

    return { ok: response.ok, status: response.status, data };
}

export function xsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}
