import { formatArea, formatDistance, formatNumber, formatRupiah } from '../format';
import type { Category, ParcelSummary, SortOrder } from '../types';

const SORT_LABELS: Record<SortOrder, string> = {
    price_asc: 'Harga/m² terendah',
    price_desc: 'Harga/m² tertinggi',
    area_desc: 'Luas terbesar',
    area_asc: 'Luas terkecil',
    nearest: 'Jarak terdekat',
};

interface ResultListProps {
    parcels: ParcelSummary[];
    total: number;
    hasMore: boolean;
    isLoading: boolean;
    error: string | null;
    sort: SortOrder;
    canSortByDistance: boolean;
    selectedId: number | null;
    landUses: Category[];
    statuses: Category[];
    onSortChange: (sort: SortOrder) => void;
    onSelect: (parcel: ParcelSummary) => void;
    onHighlight: (id: number | null) => void;
    onLoadMore: () => void;
}

export default function ResultList({
    parcels,
    total,
    hasMore,
    isLoading,
    error,
    sort,
    canSortByDistance,
    selectedId,
    landUses,
    statuses,
    onSortChange,
    onSelect,
    onHighlight,
    onLoadMore,
}: ResultListProps) {
    const sortOptions = (Object.keys(SORT_LABELS) as SortOrder[]).filter((option) => option !== 'nearest' || canSortByDistance);

    return (
        <section className="flex flex-col" aria-label="Hasil pencarian">
            <div className="flex items-center justify-between gap-2 px-4 pt-4 pb-2">
                <h2 className="text-sm font-semibold text-slate-900" aria-live="polite">
                    {formatNumber(total)} bidang
                    {isLoading && <span className="ml-2 text-xs font-normal text-slate-400">memuat…</span>}
                </h2>
                <label className="flex items-center gap-1.5 text-xs text-slate-600">
                    <span className="sr-only">Urutkan</span>
                    <select
                        className="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-900 focus:border-blue-600 focus:outline-none"
                        value={sort}
                        onChange={(event) => onSortChange(event.target.value as SortOrder)}
                    >
                        {sortOptions.map((option) => (
                            <option key={option} value={option}>
                                {SORT_LABELS[option]}
                            </option>
                        ))}
                    </select>
                </label>
            </div>

            {error && <p className="mx-4 rounded-md bg-red-50 px-3 py-2 text-xs text-red-700">Gagal memuat data: {error}</p>}

            {!error && !isLoading && parcels.length === 0 && (
                <p className="px-4 py-6 text-center text-sm text-slate-500">
                    Tidak ada bidang yang cocok di area ini. Geser peta atau longgarkan filter.
                </p>
            )}

            <ul className="flex flex-col">
                {parcels.map((parcel) => {
                    const landUse = landUses.find(({ value }) => value === parcel.land_use);
                    const status = statuses.find(({ value }) => value === parcel.status);

                    return (
                        <li key={parcel.id}>
                            <button
                                type="button"
                                aria-current={parcel.id === selectedId}
                                className={`flex w-full flex-col gap-1 border-b border-slate-100 px-4 py-3 text-left transition hover:bg-slate-50 focus-visible:bg-slate-50 focus-visible:outline-none ${
                                    parcel.id === selectedId ? 'bg-blue-50 hover:bg-blue-50' : ''
                                }`}
                                onClick={() => onSelect(parcel)}
                                onMouseEnter={() => onHighlight(parcel.id)}
                                onMouseLeave={() => onHighlight(null)}
                                onFocus={() => onHighlight(parcel.id)}
                                onBlur={() => onHighlight(null)}
                            >
                                <span className="flex items-center justify-between gap-2">
                                    <span className="flex items-center gap-2 text-sm font-semibold text-slate-900">
                                        <span
                                            className="size-2.5 rounded-full ring-1 ring-black/10"
                                            style={{ backgroundColor: landUse?.color }}
                                        />
                                        {parcel.parcel_number}
                                    </span>
                                    <span className="text-sm font-semibold text-slate-900 tabular-nums">{formatRupiah(parcel.total_price)}</span>
                                </span>
                                <span className="flex items-center justify-between gap-2 text-xs text-slate-500">
                                    <span>
                                        {landUse?.label ?? parcel.land_use} · {formatArea(parcel.area_sqm)}
                                        {parcel.distance_m !== undefined && ` · ${formatDistance(parcel.distance_m)}`}
                                    </span>
                                    <span className="tabular-nums">{formatRupiah(parcel.price_per_sqm)}/m²</span>
                                </span>
                                <span className="flex items-center gap-1.5 text-xs text-slate-600">
                                    <span className="size-1.5 rounded-full" style={{ backgroundColor: status?.color }} />
                                    {status?.label ?? parcel.status}
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ul>

            {hasMore && (
                <button
                    type="button"
                    className="m-4 rounded-md border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:border-slate-400 disabled:text-slate-400"
                    disabled={isLoading}
                    onClick={onLoadMore}
                >
                    Muat lebih banyak
                </button>
            )}
        </section>
    );
}
