/** Minimal JSON fetch helper for same-origin Laravel routes (CSRF via the XSRF-TOKEN cookie). */
export class HttpError extends Error {
    constructor(
        public status: number,
        public data: Record<string, unknown>,
    ) {
        super((data?.message as string) || (data?.error as string) || `Request failed (${status})`);
    }
}

function xsrf(): string {
    return decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

export async function http<T = unknown>(url: string, init: RequestInit & { json?: unknown } = {}): Promise<T> {
    const { json, headers, ...rest } = init;
    const res = await fetch(url, {
        credentials: 'same-origin',
        ...rest,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrf(),
            ...(json !== undefined ? { 'Content-Type': 'application/json' } : {}),
            ...(headers ?? {}),
        },
        body: json !== undefined ? JSON.stringify(json) : rest.body,
    });
    const data = res.status === 204 ? {} : await res.json().catch(() => ({}));
    if (!res.ok) throw new HttpError(res.status, data);
    return data as T;
}
