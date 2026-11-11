import { useCallback, useEffect, useRef, useState } from 'react';
import { getJson } from './api';
import type { Paginated, ParcelSummary } from './types';

export function useDebouncedValue<T>(value: T, delayMs: number): T {
    const [debounced, setDebounced] = useState(value);

    useEffect(() => {
        const timeout = window.setTimeout(() => setDebounced(value), delayMs);

        return () => window.clearTimeout(timeout);
    }, [value, delayMs]);

    return debounced;
}

interface FetchState<T> {
    data: T | null;
    error: string | null;
    isLoading: boolean;
}

/**
 * Fetch JSON whenever the URL changes, cancelling the request it supersedes.
 * The previous data stays visible while the next response is on its way.
 */
export function useFetch<T>(url: string | null): FetchState<T> {
    const [state, setState] = useState<FetchState<T>>({ data: null, error: null, isLoading: url !== null });

    useEffect(() => {
        if (url === null) {
            setState({ data: null, error: null, isLoading: false });

            return;
        }

        const controller = new AbortController();
        setState((previous) => ({ ...previous, error: null, isLoading: true }));

        getJson<T>(url, controller.signal)
            .then((data) => setState({ data, error: null, isLoading: false }))
            .catch((error: Error) => {
                if (error.name !== 'AbortError') {
                    setState({ data: null, error: error.message, isLoading: false });
                }
            });

        return () => controller.abort();
    }, [url]);

    return state;
}

interface ParcelSearchState {
    parcels: ParcelSummary[];
    total: number;
    page: number;
    lastPage: number;
    error: string | null;
    isLoading: boolean;
}

const EMPTY_SEARCH: ParcelSearchState = { parcels: [], total: 0, page: 0, lastPage: 0, error: null, isLoading: true };

/**
 * Paginated parcel search. A new URL restarts from the first page; "loadMore"
 * appends the next one.
 */
export function useParcelSearch(url: string | null): ParcelSearchState & { loadMore: () => void } {
    const [state, setState] = useState<ParcelSearchState>(EMPTY_SEARCH);
    const controller = useRef<AbortController | null>(null);

    const loadPage = useCallback(
        (page: number) => {
            if (url === null) {
                return;
            }

            controller.current?.abort();
            const current = new AbortController();
            controller.current = current;
            setState((previous) => ({ ...previous, error: null, isLoading: true }));

            getJson<Paginated<ParcelSummary>>(`${url}${url.includes('?') ? '&' : '?'}page=${page}`, current.signal)
                .then(({ data, meta }) =>
                    setState((previous) => ({
                        parcels: page === 1 ? data : [...previous.parcels, ...data],
                        total: meta.total,
                        page: meta.current_page,
                        lastPage: meta.last_page,
                        error: null,
                        isLoading: false,
                    })),
                )
                .catch((error: Error) => {
                    if (error.name !== 'AbortError') {
                        setState((previous) => ({ ...previous, error: error.message, isLoading: false }));
                    }
                });
        },
        [url],
    );

    useEffect(() => {
        loadPage(1);

        return () => controller.current?.abort();
    }, [loadPage]);

    const loadMore = useCallback(() => loadPage(state.page + 1), [loadPage, state.page]);

    return { ...state, loadMore };
}
