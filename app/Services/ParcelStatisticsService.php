<?php

namespace App\Services;

use App\Data\ParcelFilters;
use App\Models\Parcel;

class ParcelStatisticsService
{
    /**
     * Aggregate the parcels matching a set of filters.
     *
     * @return array{
     *     parcel_count: int,
     *     total_area_sqm: float,
     *     average_price_per_sqm: int|null,
     *     median_price_per_sqm: int|null,
     *     by_land_use: list<array{land_use: string, parcel_count: int, average_price_per_sqm: int}>
     * }
     */
    public function summarize(ParcelFilters $filters): array
    {
        $totals = Parcel::query()
            ->filteredBy($filters)
            ->toBase()
            ->selectRaw('count(*) AS parcel_count')
            ->selectRaw('coalesce(sum(area_sqm), 0) AS total_area_sqm')
            ->selectRaw('avg(price_per_sqm) AS average_price_per_sqm')
            ->selectRaw('percentile_cont(0.5) WITHIN GROUP (ORDER BY price_per_sqm) AS median_price_per_sqm')
            ->first();

        $byLandUse = Parcel::query()
            ->filteredBy($filters)
            ->toBase()
            ->select('land_use')
            ->selectRaw('count(*) AS parcel_count')
            ->selectRaw('avg(price_per_sqm) AS average_price_per_sqm')
            ->groupBy('land_use')
            ->orderByDesc('parcel_count')
            ->orderBy('land_use')
            ->get();

        return [
            'parcel_count' => (int) $totals->parcel_count,
            'total_area_sqm' => round((float) $totals->total_area_sqm, 1),
            'average_price_per_sqm' => $this->roundedOrNull($totals->average_price_per_sqm),
            'median_price_per_sqm' => $this->roundedOrNull($totals->median_price_per_sqm),
            'by_land_use' => $byLandUse
                ->map(fn (object $group): array => [
                    'land_use' => $group->land_use,
                    'parcel_count' => (int) $group->parcel_count,
                    'average_price_per_sqm' => (int) round((float) $group->average_price_per_sqm),
                ])
                ->all(),
        ];
    }

    /**
     * Bounding box of every parcel as west, south, east, north.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    public function extent(): ?array
    {
        $extent = Parcel::query()
            ->toBase()
            ->selectRaw('ST_XMin(ST_Extent(boundary)) AS west, ST_YMin(ST_Extent(boundary)) AS south')
            ->selectRaw('ST_XMax(ST_Extent(boundary)) AS east, ST_YMax(ST_Extent(boundary)) AS north')
            ->first();

        if ($extent->west === null) {
            return null;
        }

        return [(float) $extent->west, (float) $extent->south, (float) $extent->east, (float) $extent->north];
    }

    /**
     * Price per square metre at the 0th, 20th, 40th, 60th, 80th and 100th
     * percentiles, used as class breaks for the price colour ramp.
     *
     * @return list<int>
     */
    public function pricePerSqmBreaks(): array
    {
        $breaks = Parcel::query()
            ->toBase()
            ->selectRaw('array_to_json(percentile_cont(ARRAY[0, 0.2, 0.4, 0.6, 0.8, 1]) WITHIN GROUP (ORDER BY price_per_sqm)) AS breaks')
            ->value('breaks');

        return array_map(
            fn (float|int $break): int => (int) round($break),
            json_decode($breaks ?? '[]', true),
        );
    }

    private function roundedOrNull(mixed $value): ?int
    {
        return $value === null ? null : (int) round((float) $value);
    }
}
