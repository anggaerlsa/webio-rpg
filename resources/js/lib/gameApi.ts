// Minimal JSON fetch helpers for the in-combat turn loop.
// Uses the XSRF-TOKEN cookie (refreshed by Laravel on every response) so it
// always carries a valid CSRF token, even after a session is regenerated.

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function failure(res: Response): Promise<never> {
    let message = `Request failed (${res.status})`;
    try {
        const data = await res.json();
        if (data?.message) message = data.message;
    } catch {
        // ignore non-JSON bodies
    }
    throw new Error(message);
}

export async function postJson<T = unknown>(url: string, body: Record<string, unknown>): Promise<T> {
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });
    if (!res.ok) return failure(res);
    return res.json() as Promise<T>;
}

export async function getJson<T = unknown>(url: string): Promise<T> {
    const res = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!res.ok) return failure(res);
    return res.json() as Promise<T>;
}

export async function deleteJson<T = unknown>(url: string): Promise<T> {
    const res = await fetch(url, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    if (!res.ok) return failure(res);
    return res.json() as Promise<T>;
}
