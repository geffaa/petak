<?php

namespace Database\Seeders;

use App\Enums\CertificateType;
use App\Enums\LandUse;
use App\Enums\ParcelStatus;
use App\Models\Parcel;
use App\Services\ParcelTileService;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Lays out a synthetic neighbourhood: a rotated street grid whose blocks are
 * subdivided into lots, with land use and prices that vary by location.
 * The data is invented for demonstration and describes no real property.
 */
class ParcelSeeder extends Seeder
{
    private const int BLOCK_COLUMNS = 9;

    private const int BLOCK_ROWS = 10;

    private const float BLOCK_WIDTH = 132.0;

    private const float BLOCK_DEPTH = 72.0;

    private const float STREET_WIDTH = 10.0;

    private const float AVENUE_WIDTH = 22.0;

    /** The two avenues run between this block index and the next one, on both axes. */
    private const int AVENUE_AFTER_INDEX = 4;

    private const float METERS_PER_DEGREE_LATITUDE = 110_574.0;

    private const float METERS_PER_DEGREE_LONGITUDE_AT_EQUATOR = 111_320.0;

    private const int PRICE_STEP = 50_000;

    private Randomizer $randomizer;

    /**
     * Run the database seeds.
     */
    public function run(ParcelTileService $tiles): void
    {
        if (Parcel::query()->exists()) {
            $this->command?->warn('Parcels already exist; run "php artisan migrate:fresh --seed" to regenerate them.');

            return;
        }

        $this->randomizer = new Randomizer(new Mt19937(config('webgis.demo.seed')));

        $parcels = [];

        for ($row = 0; $row < self::BLOCK_ROWS; $row++) {
            for ($column = 0; $column < self::BLOCK_COLUMNS; $column++) {
                array_push($parcels, ...$this->parcelsForBlock($row, $column));
            }
        }

        foreach (array_chunk($parcels, 250) as $chunk) {
            Parcel::insert($chunk);
        }

        $tiles->flush();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parcelsForBlock(int $row, int $column): array
    {
        $landUse = $this->landUseForBlock($row, $column);
        $block = sprintf('%s%02d', chr(ord('A') + $row), $column + 1);
        [$minFrontage, $maxFrontage, $lotRows] = $this->lotLayoutFor($landUse);

        $blockWest = $this->offsetAlongAxis($column, self::BLOCK_WIDTH, self::BLOCK_COLUMNS);
        $blockSouth = $this->offsetAlongAxis($row, self::BLOCK_DEPTH, self::BLOCK_ROWS);
        $lotDepth = self::BLOCK_DEPTH / $lotRows;

        $parcels = [];
        $sequence = 1;

        for ($lotRow = 0; $lotRow < $lotRows; $lotRow++) {
            $west = $blockWest;
            $south = $blockSouth + $lotRow * $lotDepth;

            foreach ($this->frontages($minFrontage, $maxFrontage) as $frontage) {
                $now = now();

                $parcels[] = [
                    'parcel_number' => sprintf('%s-%03d', $block, $sequence++),
                    'block' => $block,
                    'land_use' => $landUse->value,
                    'status' => $this->pickWeighted([
                        ParcelStatus::Available->value => 62,
                        ParcelStatus::Reserved->value => 14,
                        ParcelStatus::Sold->value => 24,
                    ]),
                    'certificate_type' => $this->pickWeighted($this->certificateWeightsFor($landUse)),
                    'price_per_sqm' => $this->pricePerSqm($landUse, $west + $frontage / 2, $south + $lotDepth / 2),
                    'boundary' => $this->rectangle($west, $south, $west + $frontage, $south + $lotDepth),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $west += $frontage;
            }
        }

        return $parcels;
    }

    private function landUseForBlock(int $row, int $column): LandUse
    {
        $avenueIndexes = [self::AVENUE_AFTER_INDEX, self::AVENUE_AFTER_INDEX + 1];
        $facesNorthSouthAvenue = in_array($column, $avenueIndexes, true);
        $facesEastWestAvenue = in_array($row, $avenueIndexes, true);

        if ($facesNorthSouthAvenue && $facesEastWestAvenue) {
            return LandUse::Commercial;
        }

        if ($facesNorthSouthAvenue || $facesEastWestAvenue) {
            return $this->randomizer->getInt(1, 100) <= 45 ? LandUse::Commercial : LandUse::MixedUse;
        }

        if ($column === self::BLOCK_COLUMNS - 1) {
            return LandUse::Industrial;
        }

        if ($column <= 2 && ($row === 0 || $row === self::BLOCK_ROWS - 1)) {
            return LandUse::Agricultural;
        }

        return LandUse::Residential;
    }

    /**
     * @return array{0: float, 1: float, 2: int} Minimum frontage, maximum frontage, and rows of lots per block.
     */
    private function lotLayoutFor(LandUse $landUse): array
    {
        return match ($landUse) {
            LandUse::Residential => [10.0, 18.0, 2],
            LandUse::MixedUse => [12.0, 20.0, 2],
            LandUse::Commercial => [18.0, 30.0, 2],
            LandUse::Industrial => [30.0, 44.0, 1],
            LandUse::Agricultural => [40.0, 66.0, 1],
        };
    }

    /**
     * Split a block's width into lot frontages; the last lot absorbs any remainder too small to stand alone.
     *
     * @return list<float>
     */
    private function frontages(float $minimum, float $maximum): array
    {
        $frontages = [];
        $remaining = self::BLOCK_WIDTH;

        while ($remaining > 0) {
            $frontage = round($this->randomizer->getFloat($minimum, $maximum), 1);

            if ($remaining - $frontage < $minimum) {
                $frontage = $remaining;
            }

            $frontages[] = $frontage;
            $remaining = round($remaining - $frontage, 1);
        }

        return $frontages;
    }

    /**
     * @return array<string, int>
     */
    private function certificateWeightsFor(LandUse $landUse): array
    {
        return match ($landUse) {
            LandUse::Agricultural => [CertificateType::Shm->value => 55, CertificateType::Girik->value => 45],
            LandUse::Commercial, LandUse::Industrial => [CertificateType::Hgb->value => 60, CertificateType::Shm->value => 40],
            default => [CertificateType::Shm->value => 68, CertificateType::Hgb->value => 27, CertificateType::Girik->value => 5],
        };
    }

    /**
     * Price falls with distance from the central crossing, with some noise.
     */
    private function pricePerSqm(LandUse $landUse, float $x, float $y): int
    {
        $basePrice = match ($landUse) {
            LandUse::Residential => 4_500_000,
            LandUse::MixedUse => 8_000_000,
            LandUse::Commercial => 12_000_000,
            LandUse::Industrial => 3_500_000,
            LandUse::Agricultural => 1_200_000,
        };

        $maximumDistance = hypot($this->totalLength(self::BLOCK_WIDTH, self::BLOCK_COLUMNS), $this->totalLength(self::BLOCK_DEPTH, self::BLOCK_ROWS)) / 2;
        $locationFactor = 1.35 - 0.6 * (hypot($x, $y) / $maximumDistance);
        $noise = $this->randomizer->getFloat(0.88, 1.12);

        return (int) (round($basePrice * $locationFactor * $noise / self::PRICE_STEP) * self::PRICE_STEP);
    }

    /**
     * Distance in metres from the neighbourhood centre to the start of a block along one axis.
     */
    private function offsetAlongAxis(int $index, float $blockLength, int $blockCount): float
    {
        $offset = $index * ($blockLength + self::STREET_WIDTH);

        if ($index > self::AVENUE_AFTER_INDEX) {
            $offset += self::AVENUE_WIDTH - self::STREET_WIDTH;
        }

        return $offset - $this->totalLength($blockLength, $blockCount) / 2;
    }

    private function totalLength(float $blockLength, int $blockCount): float
    {
        return $blockCount * $blockLength + ($blockCount - 2) * self::STREET_WIDTH + self::AVENUE_WIDTH;
    }

    /**
     * Turn a rectangle in local metres into a rotated WGS 84 polygon.
     */
    private function rectangle(float $west, float $south, float $east, float $north): Expression
    {
        $corners = [[$west, $south], [$east, $south], [$east, $north], [$west, $north], [$west, $south]];

        $ring = array_map(function (array $corner): string {
            [$longitude, $latitude] = $this->toCoordinate(...$corner);

            return sprintf('%.9F %.9F', $longitude, $latitude);
        }, $corners);

        return DB::raw(sprintf("ST_GeomFromText('POLYGON((%s))', 4326)", implode(', ', $ring)));
    }

    /**
     * @return array{0: float, 1: float} Longitude and latitude.
     */
    private function toCoordinate(float $x, float $y): array
    {
        [$centerLongitude, $centerLatitude] = config('webgis.demo.center');
        $rotation = deg2rad(config('webgis.demo.rotation_degrees'));

        $east = $x * cos($rotation) - $y * sin($rotation);
        $north = $x * sin($rotation) + $y * cos($rotation);

        return [
            $centerLongitude + $east / (self::METERS_PER_DEGREE_LONGITUDE_AT_EQUATOR * cos(deg2rad($centerLatitude))),
            $centerLatitude + $north / self::METERS_PER_DEGREE_LATITUDE,
        ];
    }

    /**
     * @param  array<string, int>  $weights
     */
    private function pickWeighted(array $weights): string
    {
        $roll = $this->randomizer->getInt(1, array_sum($weights));

        foreach ($weights as $value => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $value;
            }
        }

        return array_key_last($weights);
    }
}
