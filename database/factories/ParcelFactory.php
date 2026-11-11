<?php

namespace Database\Factories;

use App\Enums\CertificateType;
use App\Enums\LandUse;
use App\Enums\ParcelStatus;
use App\Models\Parcel;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Parcel>
 */
class ParcelFactory extends Factory
{
    private const float METERS_PER_DEGREE_LATITUDE = 110_574.0;

    private const float METERS_PER_DEGREE_LONGITUDE_AT_EQUATOR = 111_320.0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parcel_number' => fake()->unique()->bothify('?##-###'),
            'block' => fake()->bothify('?##'),
            'land_use' => fake()->randomElement(LandUse::cases()),
            'status' => fake()->randomElement(ParcelStatus::cases()),
            'certificate_type' => fake()->randomElement(CertificateType::cases()),
            'price_per_sqm' => fake()->numberBetween(20, 300) * 50_000,
            'boundary' => self::square(110.4085, -7.7420, 20),
        ];
    }

    /**
     * Place the parcel as a square centred on a coordinate.
     */
    public function squareAt(float $longitude, float $latitude, float $sideMeters = 20): static
    {
        return $this->state(fn (): array => [
            'boundary' => self::square($longitude, $latitude, $sideMeters),
        ]);
    }

    public function landUse(LandUse $landUse): static
    {
        return $this->state(fn (): array => ['land_use' => $landUse]);
    }

    public function status(ParcelStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function pricedAt(int $pricePerSqm): static
    {
        return $this->state(fn (): array => ['price_per_sqm' => $pricePerSqm]);
    }

    private static function square(float $longitude, float $latitude, float $sideMeters): Expression
    {
        $halfHeight = $sideMeters / 2 / self::METERS_PER_DEGREE_LATITUDE;
        $halfWidth = $sideMeters / 2 / (self::METERS_PER_DEGREE_LONGITUDE_AT_EQUATOR * cos(deg2rad($latitude)));

        return DB::raw(sprintf(
            'ST_MakeEnvelope(%.8F, %.8F, %.8F, %.8F, 4326)',
            $longitude - $halfWidth,
            $latitude - $halfHeight,
            $longitude + $halfWidth,
            $latitude + $halfHeight,
        ));
    }
}
