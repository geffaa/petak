<?php

namespace App\Http\Requests;

use App\Data\ParcelFilters;
use App\Enums\LandUse;
use App\Enums\ParcelSort;
use App\Enums\ParcelStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ParcelSearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accept "bbox" and "near" as comma-separated strings, the usual shape in map APIs.
     */
    protected function prepareForValidation(): void
    {
        foreach (['bbox', 'near'] as $coordinateList) {
            if (is_string($this->query($coordinateList))) {
                $this->merge([$coordinateList => explode(',', $this->query($coordinateList))]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bbox' => ['sometimes', 'array', 'size:4'],
            'bbox.0' => ['numeric', 'between:-180,180'],
            'bbox.1' => ['numeric', 'between:-90,90'],
            'bbox.2' => ['numeric', 'between:-180,180'],
            'bbox.3' => ['numeric', 'between:-90,90'],
            'land_use' => ['sometimes', 'array'],
            'land_use.*' => [Rule::enum(LandUse::class)],
            'status' => ['sometimes', 'array'],
            'status.*' => [Rule::enum(ParcelStatus::class)],
            'min_price_per_sqm' => ['sometimes', 'integer', 'min:0'],
            'max_price_per_sqm' => ['sometimes', 'integer', 'min:0'],
            'min_area_sqm' => ['sometimes', 'numeric', 'min:0'],
            'max_area_sqm' => ['sometimes', 'numeric', 'min:0'],
            'near' => ['required_with:radius', 'required_if:sort,'.ParcelSort::Nearest->value, 'array', 'size:2'],
            'near.0' => ['numeric', 'between:-180,180'],
            'near.1' => ['numeric', 'between:-90,90'],
            'radius' => ['required_with:near', 'numeric', 'min:1', 'max:'.config('webgis.search.max_radius_meters')],
            'sort' => ['sometimes', Rule::enum(ParcelSort::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config('webgis.search.max_per_page')],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'bbox' => 'bounding box',
            'near' => 'search centre',
            'radius' => 'search radius',
        ];
    }

    public function filters(): ParcelFilters
    {
        $validated = $this->safe();

        return new ParcelFilters(
            boundingBox: $validated->has('bbox') ? array_map(floatval(...), $validated->array('bbox')) : null,
            landUses: array_map(LandUse::from(...), $validated->array('land_use')),
            statuses: array_map(ParcelStatus::from(...), $validated->array('status')),
            minPricePerSqm: $validated->has('min_price_per_sqm') ? $validated->integer('min_price_per_sqm') : null,
            maxPricePerSqm: $validated->has('max_price_per_sqm') ? $validated->integer('max_price_per_sqm') : null,
            minAreaSqm: $validated->has('min_area_sqm') ? $validated->float('min_area_sqm') : null,
            maxAreaSqm: $validated->has('max_area_sqm') ? $validated->float('max_area_sqm') : null,
            near: $validated->has('near') ? array_map(floatval(...), $validated->array('near')) : null,
            radiusMeters: $validated->has('radius') ? $validated->float('radius') : null,
        );
    }

    public function sort(): ParcelSort
    {
        return $this->safe()->enum('sort', ParcelSort::class) ?? ParcelSort::PriceAscending;
    }

    public function perPage(): int
    {
        return $this->safe()->integer('per_page', config('webgis.search.default_per_page'));
    }
}
