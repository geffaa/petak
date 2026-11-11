import type { SearchQuery, SortOrder } from './types';

export async function getJson<T>(url: string, signal?: AbortSignal): Promise<T> {
    const response = await fetch(url, { headers: { Accept: 'application/json' }, signal });

    if (!response.ok) {
        throw new Error(`Permintaan gagal (${response.status})`);
    }

    return response.json() as Promise<T>;
}

/**
 * Serialise a search into the query string the parcel endpoints expect.
 */
export function toSearchParams(query: SearchQuery, sort?: SortOrder): URLSearchParams {
    const params = new URLSearchParams();
    const { filters, radius, bbox } = query;

    if (bbox) {
        params.set('bbox', bbox.join(','));
    }

    filters.landUses.forEach((landUse) => params.append('land_use[]', landUse));
    filters.statuses.forEach((status) => params.append('status[]', status));

    const ranges: [string, number | null][] = [
        ['min_price_per_sqm', filters.minPricePerSqm],
        ['max_price_per_sqm', filters.maxPricePerSqm],
        ['min_area_sqm', filters.minAreaSqm],
        ['max_area_sqm', filters.maxAreaSqm],
    ];

    ranges.forEach(([name, value]) => {
        if (value !== null) {
            params.set(name, String(value));
        }
    });

    if (radius) {
        params.set('near', radius.center.join(','));
        params.set('radius', String(radius.radiusMeters));
    }

    if (sort) {
        params.set('sort', sort);
    }

    return params;
}
