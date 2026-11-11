import type { Feature, Polygon } from 'geojson';

/** West, south, east, north in WGS 84. */
export type Bbox = [number, number, number, number];

/** Longitude, latitude. */
export type Coordinate = [number, number];

export interface Endpoints {
    map: string;
    parcels: string;
    statistics: string;
}

export interface Category {
    value: string;
    label: string;
    color: string;
}

export interface MapMetadata {
    extent: Bbox | null;
    basemap_style_url: string;
    tiles: {
        url: string;
        layer: string;
        min_zoom: number;
        max_zoom: number;
    };
    search: {
        max_radius_meters: number;
    };
    land_uses: Category[];
    statuses: Category[];
    price_per_sqm_breaks: number[];
}

export interface ParcelSummary {
    id: number;
    parcel_number: string;
    block: string;
    land_use: string;
    status: string;
    certificate_type: string;
    area_sqm: number;
    price_per_sqm: number;
    total_price: number;
    label_point: Coordinate;
    distance_m?: number;
}

export interface ParcelProperties {
    parcel_number: string;
    block: string;
    land_use: string;
    status: string;
    certificate_type: string;
    certificate_label: string;
    area_sqm: number;
    price_per_sqm: number;
    total_price: number;
    updated_at: string;
}

export type ParcelFeature = Feature<Polygon, ParcelProperties> & { id: number; bbox: Bbox };

export interface Statistics {
    parcel_count: number;
    total_area_sqm: number;
    average_price_per_sqm: number | null;
    median_price_per_sqm: number | null;
    by_land_use: { land_use: string; parcel_count: number; average_price_per_sqm: number }[];
}

export interface Paginated<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
    };
}

export interface Filters {
    landUses: string[];
    statuses: string[];
    minPricePerSqm: number | null;
    maxPricePerSqm: number | null;
    minAreaSqm: number | null;
    maxAreaSqm: number | null;
}

export interface RadiusSearch {
    center: Coordinate;
    radiusMeters: number;
}

export type SortOrder = 'price_asc' | 'price_desc' | 'area_asc' | 'area_desc' | 'nearest';

export type ColorMode = 'land_use' | 'price' | 'status';

export interface SearchQuery {
    filters: Filters;
    radius: RadiusSearch | null;
    bbox: Bbox | null;
}

export const EMPTY_FILTERS: Filters = {
    landUses: [],
    statuses: [],
    minPricePerSqm: null,
    maxPricePerSqm: null,
    minAreaSqm: null,
    maxAreaSqm: null,
};
