<?php

namespace App\Models;

use App\Data\ParcelFilters;
use App\Enums\CertificateType;
use App\Enums\LandUse;
use App\Enums\ParcelSort;
use App\Enums\ParcelStatus;
use App\Observers\ParcelObserver;
use Database\Factories\ParcelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['parcel_number', 'block', 'land_use', 'status', 'certificate_type', 'price_per_sqm', 'boundary'])]
#[Hidden(['boundary', 'boundary_web_mercator'])]
#[ObservedBy(ParcelObserver::class)]
class Parcel extends Model
{
    /** @use HasFactory<ParcelFactory> */
    use HasFactory;

    private const string POINT_SQL = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'land_use' => LandUse::class,
            'status' => ParcelStatus::class,
            'certificate_type' => CertificateType::class,
            'price_per_sqm' => 'integer',
            'area_sqm' => 'float',
        ];
    }

    /**
     * Asking price for the whole parcel, in rupiah.
     *
     * @return Attribute<int, never>
     */
    protected function totalPrice(): Attribute
    {
        return Attribute::get(fn (): int => (int) round($this->price_per_sqm * $this->area_sqm));
    }

    /**
     * Select the lightweight columns a result list needs, with a label point
     * instead of the full boundary.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forListing(Builder $query): void
    {
        $query
            ->select(['id', 'parcel_number', 'block', 'land_use', 'status', 'certificate_type', 'price_per_sqm', 'area_sqm'])
            ->selectRaw('ST_X(ST_PointOnSurface(boundary)) AS longitude, ST_Y(ST_PointOnSurface(boundary)) AS latitude');
    }

    /**
     * Select every column plus the boundary as GeoJSON and its bounding box.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withGeoJson(Builder $query): void
    {
        $query
            ->select('*')
            ->selectRaw('ST_AsGeoJSON(boundary, 7) AS boundary_geojson')
            ->selectRaw('ST_XMin(boundary) AS west, ST_YMin(boundary) AS south, ST_XMax(boundary) AS east, ST_YMax(boundary) AS north');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function intersectingBoundingBox(Builder $query, float $west, float $south, float $east, float $north): void
    {
        $query->whereRaw('boundary && ST_MakeEnvelope(?, ?, ?, ?, 4326)', [$west, $south, $east, $north]);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withinRadiusOf(Builder $query, float $longitude, float $latitude, float $radiusMeters): void
    {
        $query->whereRaw('ST_DWithin(boundary::geography, '.self::POINT_SQL.', ?)', [$longitude, $latitude, $radiusMeters]);
    }

    /**
     * Add the geodesic distance from a point, in metres, as "distance_m".
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withDistanceFrom(Builder $query, float $longitude, float $latitude): void
    {
        $query->selectRaw('ST_Distance(boundary::geography, '.self::POINT_SQL.') AS distance_m', [$longitude, $latitude]);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function filteredBy(Builder $query, ParcelFilters $filters): void
    {
        $query
            ->when($filters->boundingBox, fn (Builder $query, array $boundingBox) => $query->intersectingBoundingBox(...$boundingBox))
            ->when($filters->hasRadiusSearch(), fn (Builder $query) => $query->withinRadiusOf($filters->near[0], $filters->near[1], $filters->radiusMeters))
            ->when($filters->landUses, fn (Builder $query, array $landUses) => $query->whereIn('land_use', array_column($landUses, 'value')))
            ->when($filters->statuses, fn (Builder $query, array $statuses) => $query->whereIn('status', array_column($statuses, 'value')))
            ->when($filters->minPricePerSqm !== null, fn (Builder $query) => $query->where('price_per_sqm', '>=', $filters->minPricePerSqm))
            ->when($filters->maxPricePerSqm !== null, fn (Builder $query) => $query->where('price_per_sqm', '<=', $filters->maxPricePerSqm))
            ->when($filters->minAreaSqm !== null, fn (Builder $query) => $query->where('area_sqm', '>=', $filters->minAreaSqm))
            ->when($filters->maxAreaSqm !== null, fn (Builder $query) => $query->where('area_sqm', '<=', $filters->maxAreaSqm));
    }

    /**
     * Order by the requested sort, with the primary key as a stable tie-breaker.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function sortedBy(Builder $query, ParcelSort $sort): void
    {
        match ($sort) {
            ParcelSort::PriceAscending => $query->orderBy('price_per_sqm'),
            ParcelSort::PriceDescending => $query->orderByDesc('price_per_sqm'),
            ParcelSort::AreaAscending => $query->orderBy('area_sqm'),
            ParcelSort::AreaDescending => $query->orderByDesc('area_sqm'),
            ParcelSort::Nearest => $query->orderBy('distance_m'),
        };

        $query->orderBy('id');
    }
}
