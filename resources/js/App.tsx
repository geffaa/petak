import { useCallback, useEffect, useMemo, useState } from 'react';
import { toSearchParams } from './api';
import FilterPanel from './components/FilterPanel';
import Legend from './components/Legend';
import MapView, { type MapFocus } from './components/MapView';
import ParcelDetail from './components/ParcelDetail';
import ResultList from './components/ResultList';
import StatisticsPanel from './components/StatisticsPanel';
import { useDebouncedValue, useFetch, useParcelSearch } from './hooks';
import {
    EMPTY_FILTERS,
    type Bbox,
    type ColorMode,
    type Coordinate,
    type Endpoints,
    type Filters,
    type MapMetadata,
    type ParcelFeature,
    type ParcelSummary,
    type RadiusSearch,
    type SearchQuery,
    type SortOrder,
    type Statistics,
} from './types';

const DEFAULT_SORT: SortOrder = 'price_asc';
const DEFAULT_RADIUS_METERS = 300;

export default function App({ endpoints }: { endpoints: Endpoints }) {
    const metadata = useFetch<{ data: MapMetadata }>(endpoints.map);

    if (metadata.error) {
        return <Notice title="Peta tidak dapat dimuat" message={metadata.error} />;
    }

    if (!metadata.data) {
        return <Notice title="Memuat peta…" />;
    }

    return <Explorer endpoints={endpoints} metadata={metadata.data.data} />;
}

function Explorer({ endpoints, metadata }: { endpoints: Endpoints; metadata: MapMetadata }) {
    const [filters, setFilters] = useState<Filters>(EMPTY_FILTERS);
    const [radiusCenter, setRadiusCenter] = useState<Coordinate | null>(null);
    const [radiusMeters, setRadiusMeters] = useState(DEFAULT_RADIUS_METERS);
    const [isPickingCenter, setIsPickingCenter] = useState(false);
    const [bbox, setBbox] = useState<Bbox | null>(null);
    const [sort, setSort] = useState<SortOrder>(DEFAULT_SORT);
    const [colorMode, setColorMode] = useState<ColorMode>('land_use');
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [highlightedId, setHighlightedId] = useState<number | null>(null);
    const [focus, setFocus] = useState<MapFocus | null>(null);

    const radius = useMemo<RadiusSearch | null>(
        () => (radiusCenter ? { center: radiusCenter, radiusMeters } : null),
        [radiusCenter, radiusMeters],
    );

    // One debounced query drives both the list and the summary, so typing in a
    // filter or dragging the map settles before any request is sent.
    const query = useDebouncedValue(
        useMemo<SearchQuery>(() => ({ filters, radius, bbox }), [filters, radius, bbox]),
        250,
    );
    const effectiveSort = sort === 'nearest' && !query.radius ? DEFAULT_SORT : sort;
    const isMapReady = query.bbox !== null;

    const search = useParcelSearch(isMapReady ? `${endpoints.parcels}?${toSearchParams(query, effectiveSort)}` : null);
    const statistics = useFetch<{ data: Statistics }>(isMapReady ? `${endpoints.statistics}?${toSearchParams(query)}` : null);
    const selectedParcel = useFetch<ParcelFeature>(selectedId === null ? null : `${endpoints.parcels}/${selectedId}`);

    useEffect(() => {
        if (!isPickingCenter) {
            return;
        }

        const cancelOnEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setIsPickingCenter(false);
            }
        };

        window.addEventListener('keydown', cancelOnEscape);

        return () => window.removeEventListener('keydown', cancelOnEscape);
    }, [isPickingCenter]);

    const pickCenter = useCallback((center: Coordinate) => {
        setRadiusCenter(center);
        setIsPickingCenter(false);
        setSort('nearest');
    }, []);

    const clearRadius = useCallback(() => {
        setRadiusCenter(null);
        setIsPickingCenter(false);
        setSort((current) => (current === 'nearest' ? DEFAULT_SORT : current));
    }, []);

    const selectFromList = useCallback((parcel: ParcelSummary) => {
        setSelectedId(parcel.id);
        setFocus((previous) => ({ center: parcel.label_point, requestId: (previous?.requestId ?? 0) + 1 }));
    }, []);

    return (
        <div className="flex h-dvh flex-col-reverse bg-white text-slate-900 md:flex-row">
            <aside className="flex h-[52dvh] shrink-0 flex-col border-t border-slate-200 md:h-full md:w-[400px] md:border-t-0 md:border-r">
                <header className="border-b border-slate-200 px-4 py-3">
                    <h1 className="text-base font-semibold">Peta Lahan</h1>
                    <p className="text-xs text-slate-500">Eksplorasi bidang tanah · data sintetis untuk demo</p>
                </header>
                <div className="min-h-0 flex-1 overflow-y-auto">
                    <FilterPanel
                        metadata={metadata}
                        filters={filters}
                        radius={radius}
                        radiusMeters={radiusMeters}
                        isPickingCenter={isPickingCenter}
                        onFiltersChange={setFilters}
                        onRadiusMetersChange={setRadiusMeters}
                        onPickCenterToggle={() => setIsPickingCenter((current) => !current)}
                        onRadiusClear={clearRadius}
                    />
                    <StatisticsPanel statistics={statistics.data?.data ?? null} landUses={metadata.land_uses} />
                    <ResultList
                        parcels={search.parcels}
                        total={search.total}
                        hasMore={search.page < search.lastPage}
                        isLoading={search.isLoading}
                        error={search.error}
                        sort={effectiveSort}
                        canSortByDistance={radius !== null}
                        selectedId={selectedId}
                        landUses={metadata.land_uses}
                        statuses={metadata.statuses}
                        onSortChange={setSort}
                        onSelect={selectFromList}
                        onHighlight={setHighlightedId}
                        onLoadMore={search.loadMore}
                    />
                </div>
            </aside>

            <main className="relative min-h-0 flex-1">
                <MapView
                    metadata={metadata}
                    filters={filters}
                    radius={radius}
                    colorMode={colorMode}
                    selectedParcel={selectedId === null ? null : selectedParcel.data}
                    highlightedId={highlightedId}
                    focus={focus}
                    isPickingCenter={isPickingCenter}
                    onBoundsChange={setBbox}
                    onSelect={setSelectedId}
                    onPickCenter={pickCenter}
                />
                <div className="pointer-events-none absolute inset-x-3 top-3 flex flex-col items-start gap-3 md:flex-row md:justify-between">
                    <div className="pointer-events-auto">
                        <Legend metadata={metadata} colorMode={colorMode} onColorModeChange={setColorMode} />
                    </div>
                    {selectedId !== null && selectedParcel.data && (
                        <div className="pointer-events-auto w-72 max-w-full md:max-w-[calc(100%-16rem)]">
                            <ParcelDetail
                                parcel={selectedParcel.data}
                                landUses={metadata.land_uses}
                                statuses={metadata.statuses}
                                onClose={() => setSelectedId(null)}
                            />
                        </div>
                    )}
                </div>
                {isPickingCenter && (
                    <p className="pointer-events-none absolute inset-x-0 bottom-10 mx-auto w-fit rounded-full bg-slate-900 px-4 py-2 text-xs font-medium text-white shadow-lg">
                        Klik peta untuk menentukan titik pusat · Esc untuk batal
                    </p>
                )}
            </main>
        </div>
    );
}

function Notice({ title, message }: { title: string; message?: string }) {
    return (
        <div className="flex h-dvh flex-col items-center justify-center gap-1 bg-white text-slate-900">
            <p className="text-sm font-semibold">{title}</p>
            {message && <p className="text-xs text-slate-500">{message}</p>}
        </div>
    );
}
