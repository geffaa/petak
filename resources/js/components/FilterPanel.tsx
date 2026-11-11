import type { Category, Filters, MapMetadata, RadiusSearch } from '../types';
import { EMPTY_FILTERS } from '../types';

const RUPIAH_PER_MILLION = 1_000_000;

interface FilterPanelProps {
    metadata: MapMetadata;
    filters: Filters;
    radius: RadiusSearch | null;
    radiusMeters: number;
    isPickingCenter: boolean;
    onFiltersChange: (filters: Filters) => void;
    onRadiusMetersChange: (meters: number) => void;
    onPickCenterToggle: () => void;
    onRadiusClear: () => void;
}

export default function FilterPanel({
    metadata,
    filters,
    radius,
    radiusMeters,
    isPickingCenter,
    onFiltersChange,
    onRadiusMetersChange,
    onPickCenterToggle,
    onRadiusClear,
}: FilterPanelProps) {
    const toggle = (key: 'landUses' | 'statuses', value: string) => {
        const selected = filters[key];

        onFiltersChange({
            ...filters,
            [key]: selected.includes(value) ? selected.filter((item) => item !== value) : [...selected, value],
        });
    };

    const hasFilters = JSON.stringify(filters) !== JSON.stringify(EMPTY_FILTERS) || radius !== null;

    return (
        <section className="flex flex-col gap-4 border-b border-slate-200 p-4" aria-label="Filter">
            <div className="flex items-center justify-between">
                <h2 className="text-sm font-semibold text-slate-900">Filter</h2>
                <button
                    type="button"
                    className="text-xs font-medium text-blue-700 hover:underline disabled:text-slate-400 disabled:no-underline"
                    disabled={!hasFilters}
                    onClick={() => {
                        onFiltersChange(EMPTY_FILTERS);
                        onRadiusClear();
                    }}
                >
                    Atur ulang
                </button>
            </div>

            <ChipGroup
                label="Peruntukan lahan"
                categories={metadata.land_uses}
                selected={filters.landUses}
                onToggle={(value) => toggle('landUses', value)}
            />
            <ChipGroup label="Status" categories={metadata.statuses} selected={filters.statuses} onToggle={(value) => toggle('statuses', value)} />

            <RangeFields
                label="Harga per m² (juta rupiah)"
                step={0.5}
                minimum={toMillions(filters.minPricePerSqm)}
                maximum={toMillions(filters.maxPricePerSqm)}
                onChange={(minimum, maximum) =>
                    onFiltersChange({ ...filters, minPricePerSqm: fromMillions(minimum), maxPricePerSqm: fromMillions(maximum) })
                }
            />
            <RangeFields
                label="Luas (m²)"
                step={50}
                minimum={filters.minAreaSqm}
                maximum={filters.maxAreaSqm}
                onChange={(minimum, maximum) => onFiltersChange({ ...filters, minAreaSqm: minimum, maxAreaSqm: maximum })}
            />

            <fieldset className="flex flex-col gap-2">
                <legend className="mb-2 text-xs font-medium text-slate-600">Cari dalam radius</legend>
                <div className="flex gap-2">
                    <button
                        type="button"
                        aria-pressed={isPickingCenter}
                        className={`flex-1 rounded-md border px-3 py-1.5 text-xs font-medium transition ${
                            isPickingCenter
                                ? 'border-blue-600 bg-blue-600 text-white'
                                : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400'
                        }`}
                        onClick={onPickCenterToggle}
                    >
                        {isPickingCenter ? 'Klik sebuah titik di peta…' : radius ? 'Pindahkan titik pusat' : 'Pilih titik pusat di peta'}
                    </button>
                    {radius && (
                        <button
                            type="button"
                            className="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:border-slate-400"
                            onClick={onRadiusClear}
                        >
                            Hapus
                        </button>
                    )}
                </div>
                <label className="flex items-center gap-3 text-xs text-slate-600">
                    <span className="sr-only">Radius pencarian</span>
                    <input
                        type="range"
                        className="flex-1 accent-blue-600"
                        min={100}
                        max={Math.min(2000, metadata.search.max_radius_meters)}
                        step={50}
                        value={radiusMeters}
                        onChange={(event) => onRadiusMetersChange(Number(event.target.value))}
                    />
                    <span className="w-14 text-right font-medium text-slate-900 tabular-nums">{radiusMeters} m</span>
                </label>
            </fieldset>
        </section>
    );
}

function toMillions(rupiah: number | null): number | null {
    return rupiah === null ? null : rupiah / RUPIAH_PER_MILLION;
}

function fromMillions(millions: number | null): number | null {
    return millions === null ? null : Math.round(millions * RUPIAH_PER_MILLION);
}

interface ChipGroupProps {
    label: string;
    categories: Category[];
    selected: string[];
    onToggle: (value: string) => void;
}

function ChipGroup({ label, categories, selected, onToggle }: ChipGroupProps) {
    return (
        <fieldset>
            <legend className="mb-2 text-xs font-medium text-slate-600">{label}</legend>
            <div className="flex flex-wrap gap-1.5">
                {categories.map(({ value, label: categoryLabel, color }) => {
                    const isSelected = selected.includes(value);

                    return (
                        <button
                            key={value}
                            type="button"
                            aria-pressed={isSelected}
                            className={`flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs transition ${
                                isSelected
                                    ? 'border-slate-900 bg-slate-900 text-white'
                                    : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400'
                            }`}
                            onClick={() => onToggle(value)}
                        >
                            <span className="size-2.5 rounded-full ring-1 ring-black/10" style={{ backgroundColor: color }} />
                            {categoryLabel}
                        </button>
                    );
                })}
            </div>
        </fieldset>
    );
}

interface RangeFieldsProps {
    label: string;
    step: number;
    minimum: number | null;
    maximum: number | null;
    onChange: (minimum: number | null, maximum: number | null) => void;
}

function RangeFields({ label, step, minimum, maximum, onChange }: RangeFieldsProps) {
    const parse = (value: string): number | null => (value === '' || Number.isNaN(Number(value)) ? null : Math.max(0, Number(value)));
    const inputClass =
        'w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-600 focus:outline-none';

    return (
        <fieldset>
            <legend className="mb-2 text-xs font-medium text-slate-600">{label}</legend>
            <div className="flex items-center gap-2">
                <input
                    type="number"
                    inputMode="decimal"
                    min={0}
                    step={step}
                    placeholder="Min"
                    aria-label={`${label}: minimum`}
                    className={inputClass}
                    value={minimum ?? ''}
                    onChange={(event) => onChange(parse(event.target.value), maximum)}
                />
                <span className="text-slate-400">–</span>
                <input
                    type="number"
                    inputMode="decimal"
                    min={0}
                    step={step}
                    placeholder="Maks"
                    aria-label={`${label}: maksimum`}
                    className={inputClass}
                    value={maximum ?? ''}
                    onChange={(event) => onChange(minimum, parse(event.target.value))}
                />
            </div>
        </fieldset>
    );
}
