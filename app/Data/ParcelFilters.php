<?php

namespace App\Data;

use App\Enums\LandUse;
use App\Enums\ParcelStatus;

final readonly class ParcelFilters
{
    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $boundingBox  West, south, east, north in WGS 84.
     * @param  list<LandUse>  $landUses
     * @param  list<ParcelStatus>  $statuses
     * @param  array{0: float, 1: float}|null  $near  Longitude and latitude of the radius search centre.
     */
    public function __construct(
        public ?array $boundingBox = null,
        public array $landUses = [],
        public array $statuses = [],
        public ?int $minPricePerSqm = null,
        public ?int $maxPricePerSqm = null,
        public ?float $minAreaSqm = null,
        public ?float $maxAreaSqm = null,
        public ?array $near = null,
        public ?float $radiusMeters = null,
    ) {}

    public function hasRadiusSearch(): bool
    {
        return $this->near !== null && $this->radiusMeters !== null;
    }
}
