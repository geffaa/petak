<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LandUse;
use App\Enums\ParcelStatus;
use App\Http\Controllers\Controller;
use App\Services\ParcelStatisticsService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class MapMetadataController extends Controller
{
    /**
     * Describe everything the map client needs to set itself up: where the
     * data is, how to fetch tiles, and how each category is labelled and coloured.
     */
    public function __invoke(ParcelStatisticsService $statistics): JsonResource
    {
        return new JsonResource([
            'extent' => $statistics->extent(),
            'basemap_style_url' => config('webgis.basemap_style_url'),
            'tiles' => [
                'url' => $this->tileUrlTemplate(),
                'layer' => 'parcels',
                'min_zoom' => config('webgis.tiles.min_zoom'),
                'max_zoom' => config('webgis.tiles.max_zoom'),
            ],
            'search' => [
                'max_radius_meters' => config('webgis.search.max_radius_meters'),
            ],
            'land_uses' => array_map(fn (LandUse $landUse): array => [
                'value' => $landUse->value,
                'label' => $landUse->label(),
                'color' => $landUse->color(),
            ], LandUse::cases()),
            'statuses' => array_map(fn (ParcelStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->color(),
            ], ParcelStatus::cases()),
            'price_per_sqm_breaks' => $statistics->pricePerSqmBreaks(),
        ]);
    }

    /**
     * The tile route as an XYZ template. The URL generator reads "{z}" as an
     * unfilled route parameter, so the placeholders are swapped in afterwards.
     */
    private function tileUrlTemplate(): string
    {
        $url = route('api.v1.parcels.tiles', ['zoom' => 0, 'column' => 0, 'row' => 0]);

        return Str::replaceLast('/0/0/0.mvt', '/{z}/{x}/{y}.mvt', $url);
    }
}
