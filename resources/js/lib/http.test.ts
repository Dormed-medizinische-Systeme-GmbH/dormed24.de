import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fetchJson } from '@/lib/http';

function setXsrfCookie(value: string): void {
    document.cookie = `XSRF-TOKEN=${encodeURIComponent(value)}`;
}

function jsonResponse(
    body: unknown,
    init: { ok?: boolean; status?: number } = {},
) {
    return {
        ok: init.ok ?? true,
        status: init.status ?? 200,
        json: () => Promise.resolve(body),
    } as Response;
}

describe('fetchJson', () => {
    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT';
        vi.stubGlobal('fetch', vi.fn());
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('sends the decoded XSRF-TOKEN cookie as X-XSRF-TOKEN header', async () => {
        setXsrfCookie('token with spaces');
        vi.mocked(fetch).mockResolvedValueOnce(jsonResponse({}));

        await fetchJson('/paypal/order/create');

        const [, requestInit] = vi.mocked(fetch).mock.calls[0];
        expect(requestInit?.headers).toMatchObject({
            'X-XSRF-TOKEN': 'token with spaces',
        });
    });

    it('sends an empty XSRF token when no cookie is set', async () => {
        vi.mocked(fetch).mockResolvedValueOnce(jsonResponse({}));

        await fetchJson('/some/url');

        const [, requestInit] = vi.mocked(fetch).mock.calls[0];
        expect(requestInit?.headers).toMatchObject({ 'X-XSRF-TOKEN': '' });
    });

    it('defaults to GET and omits the request body', async () => {
        setXsrfCookie('abc');
        vi.mocked(fetch).mockResolvedValueOnce(jsonResponse({}));

        await fetchJson('/some/url');

        const [, requestInit] = vi.mocked(fetch).mock.calls[0];
        expect(requestInit?.method).toBe('GET');
        expect(requestInit?.body).toBeUndefined();
        expect(requestInit?.headers).not.toHaveProperty('Content-Type');
    });

    it('JSON-encodes the body and sets Content-Type for POST requests', async () => {
        setXsrfCookie('abc');
        vi.mocked(fetch).mockResolvedValueOnce(jsonResponse({}));

        await fetchJson('/paypal/order/capture', {
            method: 'POST',
            body: { paypal_order_id: 'PAY-1' },
        });

        const [, requestInit] = vi.mocked(fetch).mock.calls[0];
        expect(requestInit?.method).toBe('POST');
        expect(requestInit?.body).toBe(
            JSON.stringify({ paypal_order_id: 'PAY-1' }),
        );
        expect(requestInit?.headers).toMatchObject({
            'Content-Type': 'application/json',
        });
    });

    it('resolves ok/status/data from a successful response', async () => {
        setXsrfCookie('abc');
        vi.mocked(fetch).mockResolvedValueOnce(
            jsonResponse({ id: 'PAY-1', status: 'CREATED' }),
        );

        const result = await fetchJson('/paypal/order/create');

        expect(result).toEqual({
            ok: true,
            status: 200,
            data: { id: 'PAY-1', status: 'CREATED' },
        });
    });

    it('surfaces error status without throwing when the response is not ok', async () => {
        setXsrfCookie('abc');
        vi.mocked(fetch).mockResolvedValueOnce(
            jsonResponse(
                { error: 'CSRF token mismatch.' },
                { ok: false, status: 419 },
            ),
        );

        const result = await fetchJson('/paypal/order/create', {
            method: 'POST',
        });

        expect(result.ok).toBe(false);
        expect(result.status).toBe(419);
        expect(result.data).toEqual({ error: 'CSRF token mismatch.' });
    });

    it('falls back to an empty object when the response body is not valid JSON', async () => {
        setXsrfCookie('abc');
        vi.mocked(fetch).mockResolvedValueOnce({
            ok: true,
            status: 204,
            json: () =>
                Promise.reject(new Error('Unexpected end of JSON input')),
        } as Response);

        const result = await fetchJson('/some/url');

        expect(result.data).toEqual({});
    });
});
