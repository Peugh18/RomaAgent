export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

/**
 * Normaliza respuestas paginadas de Laravel API Resources ({ data, meta }) y respuestas planas legacy.
 */
export function normalizeLaravelPagination<T>(payload: unknown): PaginatedResponse<T> {
    if (!payload || typeof payload !== 'object') {
        return {
            data: [],
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 0,
        };
    }

    const raw = payload as Record<string, unknown>;
    const meta = (raw.meta ?? {}) as Record<string, unknown>;
    const data = Array.isArray(raw.data) ? (raw.data as T[]) : [];

    const total = Number(meta.total ?? raw.total ?? data.length);
    const perPage = Number(meta.per_page ?? raw.per_page ?? (data.length || 20));
    const currentPage = Number(meta.current_page ?? raw.current_page ?? 1);
    const lastPage = Number(meta.last_page ?? raw.last_page ?? Math.max(1, Math.ceil(total / perPage)));

    return {
        data,
        current_page: currentPage,
        last_page: lastPage,
        per_page: perPage,
        total,
    };
}
