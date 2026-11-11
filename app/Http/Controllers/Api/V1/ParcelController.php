<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ParcelSearchRequest;
use App\Http\Resources\ParcelFeatureResource;
use App\Http\Resources\ParcelResource;
use App\Models\Parcel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ParcelController extends Controller
{
    /**
     * Search parcels by map extent, radius and attributes.
     */
    public function index(ParcelSearchRequest $request): AnonymousResourceCollection
    {
        $filters = $request->filters();

        $parcels = Parcel::query()
            ->forListing()
            ->filteredBy($filters)
            ->when($filters->hasRadiusSearch(), fn (Builder $query) => $query->withDistanceFrom(...$filters->near))
            ->sortedBy($request->sort())
            ->paginate($request->perPage())
            ->withQueryString();

        return ParcelResource::collection($parcels);
    }

    /**
     * Show one parcel as a GeoJSON Feature.
     */
    public function show(int $parcel): ParcelFeatureResource
    {
        return new ParcelFeatureResource(
            Parcel::query()->withGeoJson()->findOrFail($parcel),
        );
    }
}
