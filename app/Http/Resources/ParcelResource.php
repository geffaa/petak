<?php

namespace App\Http\Resources;

use App\Models\Parcel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Parcel
 */
class ParcelResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parcel_number' => $this->parcel_number,
            'block' => $this->block,
            'land_use' => $this->land_use,
            'status' => $this->status,
            'certificate_type' => $this->certificate_type,
            'area_sqm' => round($this->area_sqm, 1),
            'price_per_sqm' => $this->price_per_sqm,
            'total_price' => $this->total_price,
            'label_point' => [round((float) $this->longitude, 7), round((float) $this->latitude, 7)],
            'distance_m' => $this->whenHas('distance_m', fn (): float => round((float) $this->distance_m, 1)),
        ];
    }
}
