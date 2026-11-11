import { formatArea, formatRupiah } from '../format';
import type { Category, ParcelFeature } from '../types';

interface ParcelDetailProps {
    parcel: ParcelFeature;
    landUses: Category[];
    statuses: Category[];
    onClose: () => void;
}

export default function ParcelDetail({ parcel, landUses, statuses, onClose }: ParcelDetailProps) {
    const { properties } = parcel;
    const landUse = landUses.find(({ value }) => value === properties.land_use);
    const status = statuses.find(({ value }) => value === properties.status);

    return (
        <article className="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-lg ring-1 ring-black/5" aria-label={`Bidang ${properties.parcel_number}`}>
            <header className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-xs text-slate-500">Bidang · Blok {properties.block}</p>
                    <h2 className="text-lg font-semibold text-slate-900">{properties.parcel_number}</h2>
                </div>
                <button
                    type="button"
                    className="rounded-md p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                    aria-label="Tutup detail"
                    onClick={onClose}
                >
                    <svg viewBox="0 0 20 20" className="size-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                        <path d="M5 5l10 10M15 5L5 15" />
                    </svg>
                </button>
            </header>

            <div className="flex flex-wrap gap-1.5">
                <Badge color={landUse?.color} label={landUse?.label ?? properties.land_use} />
                <Badge color={status?.color} label={status?.label ?? properties.status} />
            </div>

            <div>
                <p className="text-2xl font-semibold text-slate-900 tabular-nums">{formatRupiah(properties.total_price)}</p>
                <p className="text-xs text-slate-500 tabular-nums">{formatRupiah(properties.price_per_sqm)} per m²</p>
            </div>

            <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                <dt className="text-slate-500">Luas</dt>
                <dd className="text-right text-slate-900 tabular-nums">{formatArea(properties.area_sqm)}</dd>
                <dt className="text-slate-500">Sertifikat</dt>
                <dd className="text-right text-slate-900">{properties.certificate_label}</dd>
                <dt className="text-slate-500">Titik sudut</dt>
                <dd className="text-right text-slate-900 tabular-nums">{parcel.geometry.coordinates[0].length - 1}</dd>
            </dl>
        </article>
    );
}

function Badge({ color, label }: { color?: string; label: string }) {
    return (
        <span className="flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700">
            <span className="size-2 rounded-full ring-1 ring-black/10" style={{ backgroundColor: color }} />
            {label}
        </span>
    );
}
