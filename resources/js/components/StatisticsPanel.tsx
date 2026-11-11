import { formatHectares, formatNumber, formatRupiah } from '../format';
import type { Category, Statistics } from '../types';

interface StatisticsPanelProps {
    statistics: Statistics | null;
    landUses: Category[];
}

export default function StatisticsPanel({ statistics, landUses }: StatisticsPanelProps) {
    const largestGroup = Math.max(1, ...(statistics?.by_land_use.map((group) => group.parcel_count) ?? []));

    return (
        <section className="flex flex-col gap-3 border-b border-slate-200 p-4" aria-label="Ringkasan">
            <h2 className="text-sm font-semibold text-slate-900">Ringkasan area yang tampil</h2>

            <dl className="grid grid-cols-3 gap-2">
                <Figure label="Bidang" value={statistics ? formatNumber(statistics.parcel_count) : '–'} />
                <Figure label="Total luas" value={statistics ? formatHectares(statistics.total_area_sqm) : '–'} />
                <Figure
                    label="Median /m²"
                    value={statistics?.median_price_per_sqm != null ? formatRupiah(statistics.median_price_per_sqm) : '–'}
                />
            </dl>

            {statistics && statistics.by_land_use.length > 0 && (
                <ul className="flex flex-col gap-1.5">
                    {statistics.by_land_use.map((group) => {
                        const category = landUses.find(({ value }) => value === group.land_use);

                        return (
                            <li key={group.land_use} className="grid grid-cols-[7.5rem_1fr_auto] items-center gap-2 text-xs">
                                <span className="truncate text-slate-600">{category?.label ?? group.land_use}</span>
                                <span className="h-2 overflow-hidden rounded-full bg-slate-100">
                                    <span
                                        className="block h-full rounded-full"
                                        style={{
                                            width: `${(group.parcel_count / largestGroup) * 100}%`,
                                            backgroundColor: category?.color,
                                        }}
                                    />
                                </span>
                                <span className="w-24 text-right text-slate-900 tabular-nums">
                                    {formatNumber(group.parcel_count)} · {formatRupiah(group.average_price_per_sqm)}
                                </span>
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}

function Figure({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg bg-slate-50 px-3 py-2">
            <dt className="text-[11px] text-slate-500">{label}</dt>
            <dd className="text-sm font-semibold text-slate-900 tabular-nums">{value}</dd>
        </div>
    );
}
