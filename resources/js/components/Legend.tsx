import { formatRupiah } from '../format';
import { priceClasses } from '../map/expressions';
import type { ColorMode, MapMetadata } from '../types';

const MODE_LABELS: Record<ColorMode, string> = {
    land_use: 'Peruntukan',
    price: 'Harga/m²',
    status: 'Status',
};

interface LegendProps {
    metadata: MapMetadata;
    colorMode: ColorMode;
    onColorModeChange: (mode: ColorMode) => void;
}

export default function Legend({ metadata, colorMode, onColorModeChange }: LegendProps) {
    const entries =
        colorMode === 'price'
            ? priceClasses(metadata.price_per_sqm_breaks).map(({ from, to, color }) => ({
                  key: String(from),
                  color,
                  label: `${formatRupiah(from)} – ${formatRupiah(to)}`,
              }))
            : (colorMode === 'land_use' ? metadata.land_uses : metadata.statuses).map(({ value, label, color }) => ({
                  key: value,
                  color,
                  label,
              }));

    return (
        <section className="flex w-60 flex-col gap-2.5 rounded-xl bg-white/95 p-3 shadow-lg ring-1 ring-black/5 backdrop-blur" aria-label="Legenda">
            <div className="grid grid-cols-3 rounded-lg bg-slate-100 p-0.5" role="group" aria-label="Warnai peta berdasarkan">
                {(Object.keys(MODE_LABELS) as ColorMode[]).map((mode) => (
                    <button
                        key={mode}
                        type="button"
                        aria-pressed={mode === colorMode}
                        className={`rounded-md px-1 py-1 text-[11px] font-medium transition ${
                            mode === colorMode ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900'
                        }`}
                        onClick={() => onColorModeChange(mode)}
                    >
                        {MODE_LABELS[mode]}
                    </button>
                ))}
            </div>
            <ul className="hidden flex-col gap-1 md:flex">
                {entries.map(({ key, color, label }) => (
                    <li key={key} className="flex items-center gap-2 text-xs text-slate-700 tabular-nums">
                        <span className="size-3 shrink-0 rounded-sm ring-1 ring-black/10" style={{ backgroundColor: color }} />
                        {label}
                    </li>
                ))}
                <li className="flex items-center gap-2 text-xs text-slate-500">
                    <span className="size-3 shrink-0 rounded-sm bg-slate-400/30 ring-1 ring-black/10" />
                    Di luar filter
                </li>
            </ul>
        </section>
    );
}
