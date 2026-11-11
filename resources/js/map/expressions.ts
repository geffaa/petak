import type { Feature, Polygon } from 'geojson';
import type { ExpressionSpecification, FilterSpecification } from 'maplibre-gl';
import type { Category, ColorMode, Coordinate, Filters, MapMetadata, RadiusSearch } from '../types';

const FALLBACK_COLOR = '#CBD5E1';

/** Sequential ramp for price classes, light (cheap) to dark (expensive). */
const PRICE_RAMP = ['#FEF0D9', '#FDCC8A', '#FC8D59', '#E34A33', '#B30000'];

export interface PriceClass {
    from: number;
    to: number;
    color: string;
}

/**
 * Turn the server's quantile breaks into colour classes. Duplicate breaks are
 * collapsed because a step expression needs strictly ascending stops.
 */
export function priceClasses(breaks: number[]): PriceClass[] {
    const ascending = breaks.filter((value, index) => index === 0 || value > breaks[index - 1]);

    return ascending.slice(0, -1).map((from, index) => ({
        from,
        to: ascending[index + 1],
        color: PRICE_RAMP[Math.min(index, PRICE_RAMP.length - 1)],
    }));
}

function categoryColors(property: string, categories: Category[]): ExpressionSpecification {
    return ['match', ['get', property], ...categories.flatMap(({ value, color }) => [value, color]), FALLBACK_COLOR] as unknown as ExpressionSpecification;
}

export function colorExpression(mode: ColorMode, metadata: MapMetadata): ExpressionSpecification | string {
    if (mode === 'land_use') {
        return categoryColors('land_use', metadata.land_uses);
    }

    if (mode === 'status') {
        return categoryColors('status', metadata.statuses);
    }

    const classes = priceClasses(metadata.price_per_sqm_breaks);

    if (classes.length === 0) {
        return FALLBACK_COLOR;
    }

    return [
        'step',
        ['get', 'price_per_sqm'],
        classes[0].color,
        ...classes.slice(1).flatMap(({ from, color }) => [from, color]),
    ] as ExpressionSpecification;
}

/**
 * The same filters the API applies, expressed for the vector tile layer so the
 * map and the result list always agree.
 */
export function filterExpression(filters: Filters, radius: RadiusSearch | null): FilterSpecification | null {
    const conditions: ExpressionSpecification[] = [];

    if (filters.landUses.length > 0) {
        conditions.push(['in', ['get', 'land_use'], ['literal', filters.landUses]]);
    }

    if (filters.statuses.length > 0) {
        conditions.push(['in', ['get', 'status'], ['literal', filters.statuses]]);
    }

    if (filters.minPricePerSqm !== null) {
        conditions.push(['>=', ['get', 'price_per_sqm'], filters.minPricePerSqm]);
    }

    if (filters.maxPricePerSqm !== null) {
        conditions.push(['<=', ['get', 'price_per_sqm'], filters.maxPricePerSqm]);
    }

    if (filters.minAreaSqm !== null) {
        conditions.push(['>=', ['get', 'area_sqm'], filters.minAreaSqm]);
    }

    if (filters.maxAreaSqm !== null) {
        conditions.push(['<=', ['get', 'area_sqm'], filters.maxAreaSqm]);
    }

    if (radius) {
        conditions.push(['<=', ['distance', { type: 'Point', coordinates: radius.center }], radius.radiusMeters]);
    }

    return conditions.length > 0 ? (['all', ...conditions] as FilterSpecification) : null;
}

const EARTH_RADIUS_METERS = 6_371_008.8;

/**
 * Approximate a geodesic circle with a polygon, for drawing the search radius.
 */
export function circlePolygon(center: Coordinate, radiusMeters: number, steps = 96): Feature<Polygon> {
    const [longitude, latitude] = center.map((degrees) => (degrees * Math.PI) / 180);
    const angularDistance = radiusMeters / EARTH_RADIUS_METERS;

    const ring = Array.from({ length: steps + 1 }, (_, step) => {
        const bearing = (2 * Math.PI * step) / steps;
        const pointLatitude = Math.asin(
            Math.sin(latitude) * Math.cos(angularDistance) + Math.cos(latitude) * Math.sin(angularDistance) * Math.cos(bearing),
        );
        const pointLongitude =
            longitude +
            Math.atan2(
                Math.sin(bearing) * Math.sin(angularDistance) * Math.cos(latitude),
                Math.cos(angularDistance) - Math.sin(latitude) * Math.sin(pointLatitude),
            );

        return [(pointLongitude * 180) / Math.PI, (pointLatitude * 180) / Math.PI];
    });

    return { type: 'Feature', properties: {}, geometry: { type: 'Polygon', coordinates: [ring] } };
}
