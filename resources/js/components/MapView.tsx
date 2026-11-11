import {
    Map as MapLibreMap,
    Marker,
    NavigationControl,
    Popup,
    ScaleControl,
    setWorkerUrl,
    type ExpressionSpecification,
    type GeoJSONSource,
    type MapMouseEvent,
} from 'maplibre-gl';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url';
import { useEffect, useRef, useState } from 'react';
import { formatArea, formatRupiah } from '../format';
import { circlePolygon, colorExpression, filterExpression } from '../map/expressions';
import type { Bbox, ColorMode, Coordinate, Filters, MapMetadata, ParcelFeature, RadiusSearch } from '../types';

// The bundler renames the worker file, so MapLibre cannot find it next to the main bundle on its own.
setWorkerUrl(workerUrl);

const PARCELS = 'parcels';
const SELECTED = 'selected-parcel';
const RADIUS = 'search-radius';
const FILL_LAYER = 'parcels-fill';
const MUTED_LAYER = 'parcels-muted';
const OUTLINE_LAYER = 'parcels-outline';
const EMPTY_COLLECTION = { type: 'FeatureCollection' as const, features: [] };
const INDONESIA_CENTER: Coordinate = [117.5, -2.5];

export interface MapFocus {
    center: Coordinate;
    /** Changes on every request so focusing the same parcel twice still moves the map. */
    requestId: number;
}

interface MapViewProps {
    metadata: MapMetadata;
    filters: Filters;
    radius: RadiusSearch | null;
    colorMode: ColorMode;
    selectedParcel: ParcelFeature | null;
    highlightedId: number | null;
    focus: MapFocus | null;
    isPickingCenter: boolean;
    onBoundsChange: (bbox: Bbox) => void;
    onSelect: (id: number | null) => void;
    onPickCenter: (center: Coordinate) => void;
}

export default function MapView(props: MapViewProps) {
    const { metadata, filters, radius, colorMode, selectedParcel, highlightedId, focus, isPickingCenter } = props;
    const container = useRef<HTMLDivElement>(null);
    const mapRef = useRef<MapLibreMap | null>(null);
    const centerMarker = useRef<Marker | null>(null);
    const [isLoaded, setIsLoaded] = useState(false);

    // Map event handlers are bound once, so they read the latest props through a ref.
    const latest = useRef(props);
    latest.current = props;

    useEffect(() => {
        if (!container.current) {
            return;
        }

        const map = new MapLibreMap({
            container: container.current,
            style: metadata.basemap_style_url,
            ...(metadata.extent
                ? { bounds: metadata.extent, fitBoundsOptions: { padding: 48 } }
                : { center: INDONESIA_CENTER, zoom: 4 }),
            maxZoom: 20,
            attributionControl: { compact: true },
        });
        mapRef.current = map;

        map.addControl(new NavigationControl({ showCompass: false }), 'bottom-right');
        map.addControl(new ScaleControl({ unit: 'metric' }), 'bottom-left');

        const tooltip = new Popup({ closeButton: false, closeOnClick: false, offset: 12, className: 'parcel-tooltip' });
        let hoveredId: string | number | undefined;

        const setHover = (id: string | number | undefined, hover: boolean) => {
            if (id !== undefined) {
                map.setFeatureState({ source: PARCELS, sourceLayer: metadata.tiles.layer, id }, { hover });
            }
        };

        const clearHover = () => {
            setHover(hoveredId, false);
            hoveredId = undefined;
            tooltip.remove();
            map.getCanvas().style.cursor = latest.current.isPickingCenter ? 'crosshair' : '';
        };

        const emitBounds = () => {
            const bounds = map.getBounds();
            const round = (degrees: number) => Number(degrees.toFixed(6));

            latest.current.onBoundsChange([
                round(bounds.getWest()),
                round(bounds.getSouth()),
                round(bounds.getEast()),
                round(bounds.getNorth()),
            ]);
        };

        map.on('load', () => {
            // Keep the basemap's place and street labels readable above the parcels.
            const firstLabelLayer = map.getStyle().layers.find((layer) => layer.type === 'symbol')?.id;

            map.addSource(PARCELS, {
                type: 'vector',
                tiles: [metadata.tiles.url],
                minzoom: metadata.tiles.min_zoom,
                maxzoom: metadata.tiles.max_zoom,
            });
            map.addSource(SELECTED, { type: 'geojson', data: EMPTY_COLLECTION });
            map.addSource(RADIUS, { type: 'geojson', data: EMPTY_COLLECTION });

            const isEmphasised: ExpressionSpecification = [
                'any',
                ['boolean', ['feature-state', 'hover'], false],
                ['boolean', ['feature-state', 'highlight'], false],
            ];

            map.addLayer(
                {
                    id: MUTED_LAYER,
                    type: 'fill',
                    source: PARCELS,
                    'source-layer': metadata.tiles.layer,
                    paint: { 'fill-color': '#94A3B8', 'fill-opacity': 0.22 },
                },
                firstLabelLayer,
            );
            map.addLayer(
                {
                    id: FILL_LAYER,
                    type: 'fill',
                    source: PARCELS,
                    'source-layer': metadata.tiles.layer,
                    paint: {
                        'fill-color': colorExpression(latest.current.colorMode, metadata),
                        'fill-opacity': ['case', isEmphasised, 1, 0.8],
                    },
                },
                firstLabelLayer,
            );
            map.addLayer(
                {
                    id: OUTLINE_LAYER,
                    type: 'line',
                    source: PARCELS,
                    'source-layer': metadata.tiles.layer,
                    paint: {
                        'line-color': ['case', isEmphasised, '#0F172A', '#FFFFFF'],
                        'line-width': [
                            'interpolate',
                            ['linear'],
                            ['zoom'],
                            13,
                            ['case', isEmphasised, 1.5, 0.15],
                            17,
                            ['case', isEmphasised, 2.5, 1],
                        ],
                    },
                },
                firstLabelLayer,
            );
            map.addLayer(
                {
                    id: `${RADIUS}-fill`,
                    type: 'fill',
                    source: RADIUS,
                    paint: { 'fill-color': '#2563EB', 'fill-opacity': 0.06 },
                },
                firstLabelLayer,
            );
            map.addLayer({
                id: `${RADIUS}-outline`,
                type: 'line',
                source: RADIUS,
                paint: { 'line-color': '#2563EB', 'line-width': 2, 'line-dasharray': [2, 2] },
            });
            map.addLayer({
                id: `${SELECTED}-casing`,
                type: 'line',
                source: SELECTED,
                paint: { 'line-color': '#FFFFFF', 'line-width': 6 },
            });
            map.addLayer({
                id: `${SELECTED}-outline`,
                type: 'line',
                source: SELECTED,
                paint: { 'line-color': '#0F172A', 'line-width': 3 },
            });

            setIsLoaded(true);
            emitBounds();
        });

        map.on('moveend', emitBounds);

        map.on('mousemove', FILL_LAYER, (event) => {
            const feature = event.features?.[0];

            if (!feature || latest.current.isPickingCenter) {
                return;
            }

            if (feature.id !== hoveredId) {
                setHover(hoveredId, false);
                hoveredId = feature.id;
                setHover(hoveredId, true);
            }

            map.getCanvas().style.cursor = 'pointer';
            tooltip.setLngLat(event.lngLat).setDOMContent(tooltipContent(feature.properties)).addTo(map);
        });

        map.on('mouseleave', FILL_LAYER, clearHover);

        map.on('click', (event: MapMouseEvent) => {
            if (latest.current.isPickingCenter) {
                latest.current.onPickCenter([Number(event.lngLat.lng.toFixed(6)), Number(event.lngLat.lat.toFixed(6))]);

                return;
            }

            const [feature] = map.queryRenderedFeatures(event.point, { layers: [FILL_LAYER, MUTED_LAYER] });

            latest.current.onSelect(feature?.id === undefined ? null : Number(feature.id));
        });

        return () => {
            centerMarker.current = null;
            mapRef.current = null;
            setIsLoaded(false);
            map.remove();
        };
    }, [metadata]);

    useEffect(() => {
        if (isLoaded) {
            mapRef.current?.setPaintProperty(FILL_LAYER, 'fill-color', colorExpression(colorMode, metadata));
        }
    }, [isLoaded, colorMode, metadata]);

    useEffect(() => {
        if (isLoaded) {
            mapRef.current?.setFilter(FILL_LAYER, filterExpression(filters, radius));
        }
    }, [isLoaded, filters, radius]);

    useEffect(() => {
        const map = mapRef.current;

        if (!isLoaded || !map) {
            return;
        }

        map.getSource<GeoJSONSource>(RADIUS)?.setData(radius ? circlePolygon(radius.center, radius.radiusMeters) : EMPTY_COLLECTION);
        centerMarker.current?.remove();
        centerMarker.current = radius ? new Marker({ color: '#2563EB', scale: 0.8 }).setLngLat(radius.center).addTo(map) : null;
    }, [isLoaded, radius]);

    useEffect(() => {
        if (isLoaded) {
            mapRef.current?.getSource<GeoJSONSource>(SELECTED)?.setData(selectedParcel ?? EMPTY_COLLECTION);
        }
    }, [isLoaded, selectedParcel]);

    useEffect(() => {
        const map = mapRef.current;

        if (!isLoaded || !map || highlightedId === null) {
            return;
        }

        const target = { source: PARCELS, sourceLayer: metadata.tiles.layer, id: highlightedId };
        map.setFeatureState(target, { highlight: true });

        return () => {
            // The map may already be gone when the whole view unmounts.
            if (mapRef.current === map) {
                map.setFeatureState(target, { highlight: false });
            }
        };
    }, [isLoaded, highlightedId, metadata]);

    useEffect(() => {
        const map = mapRef.current;

        if (isLoaded && map && focus) {
            map.flyTo({ center: focus.center, zoom: Math.max(map.getZoom(), 16.5), duration: 800 });
        }
    }, [isLoaded, focus]);

    useEffect(() => {
        if (mapRef.current) {
            mapRef.current.getCanvas().style.cursor = isPickingCenter ? 'crosshair' : '';
        }
    }, [isLoaded, isPickingCenter]);

    return <div ref={container} className="size-full" aria-label="Peta bidang tanah" role="application" />;
}

function tooltipContent(properties: Record<string, unknown>): HTMLElement {
    const content = document.createElement('div');
    const title = document.createElement('strong');
    const details = document.createElement('span');

    title.textContent = String(properties.parcel_number);
    details.textContent = `${formatRupiah(Number(properties.price_per_sqm))}/m² · ${formatArea(Number(properties.area_sqm))}`;
    content.append(title, details);

    return content;
}
