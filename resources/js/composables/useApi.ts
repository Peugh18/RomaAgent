import { ref } from 'vue';

export class ApiError extends Error {
    constructor(
        message: string,
        public readonly status: number,
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

function readCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
    return match ? decodeURIComponent(match[1]) : null;
}

export function getCsrfToken(): string {
    return (
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ??
        readCookie('XSRF-TOKEN') ??
        ''
    );
}

export async function apiJson<T>(url: string, init: RequestInit = {}): Promise<T> {
    const method = init.method ?? 'GET';
    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');

    if (method !== 'GET' && method !== 'HEAD') {
        if (!headers.has('Content-Type') && init.body && !(init.body instanceof FormData)) {
            headers.set('Content-Type', 'application/json');
        }
        headers.set('X-CSRF-TOKEN', getCsrfToken());
    }

    const response = await fetch(url, {
        ...init,
        method,
        headers,
        credentials: 'same-origin',
    });

    if (!response.ok) {
        if (response.status === 419) {
            throw new ApiError('Sesión expirada. Recarga la página.', 419);
        }

        const data = await response.json().catch(() => null);
        const message =
            (data && typeof data === 'object' && 'message' in data && String(data.message)) ||
            `Error ${response.status}`;

        throw new ApiError(message, response.status);
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return (await response.json()) as T;
}
