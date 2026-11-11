<?php

namespace App\Http\Resources;

use App\Models\Parcel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A parcel as a GeoJSON Feature (RFC 7946).
 *
 * @mixin Parcel
 */
class ParcelFeatureResource extends JsonResource
{
    /**
     * GeoJSON consumers expect the Feature at the top level, not under "data".
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'Feature',
            'id' => $this->id,
            'bbox' => [(float) $this->west, (float) $this->south, (float) $this->east, (float) $this->north],
            'geometry' => json_decode($this->boundary_geojson, true),
            'properties' => [
                'parcel_number' => $this->parcel_number,
                'block' => $this->block,
                'land_use' => $this->land_use,
                'status' => $this->status,
                'certificate_type' => $this->certificate_type,
                'certificate_label' => $this->certificate_type->label(),
                'area_sqm' => round($this->area_sqm, 1),
                'price_per_sqm' => $this->price_per_sqm,
                'total_price' => $this->total_price,
                'updated_at' => $this->updated_at,
            ],
        ];
    }
}
