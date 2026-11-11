<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\LandUse;
use App\Models\Parcel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParcelStatisticsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarises_the_parcels_matching_the_filters(): void
    {
        Parcel::factory()->squareAt(110.4000, -7.7400, sideMeters: 20)->landUse(LandUse::Residential)->pricedAt(4_000_000)->create();
        Parcel::factory()->squareAt(110.4005, -7.7400, sideMeters: 20)->landUse(LandUse::Residential)->pricedAt(5_000_000)->create();
        Parcel::factory()->squareAt(110.4010, -7.7400, sideMeters: 20)->landUse(LandUse::Commercial)->pricedAt(12_000_000)->create();
        Parcel::factory()->squareAt(110.4500, -7.7400, sideMeters: 20)->landUse(LandUse::Industrial)->pricedAt(1_000_000)->create();

        $response = $this->getJson('/api/v1/parcels/statistics?bbox=110.399,-7.741,110.402,-7.739');

        $response
            ->assertOk()
            ->assertJsonPath('data.parcel_count', 3)
            ->assertJsonPath('data.average_price_per_sqm', 7_000_000)
            ->assertJsonPath('data.median_price_per_sqm', 5_000_000)
            ->assertJsonPath('data.by_land_use', [
                ['land_use' => 'residential', 'parcel_count' => 2, 'average_price_per_sqm' => 4_500_000],
                ['land_use' => 'commercial', 'parcel_count' => 1, 'average_price_per_sqm' => 12_000_000],
            ]);
        $this->assertEqualsWithDelta(1200.0, $response->json('data.total_area_sqm'), 6.0);
    }

    public function test_returns_an_empty_summary_when_no_parcel_matches(): void
    {
        Parcel::factory()->landUse(LandUse::Residential)->create();

        $response = $this->getJson('/api/v1/parcels/statistics?land_use[]=industrial');

        $response
            ->assertOk()
            ->assertExactJson(['data' => [
                'parcel_count' => 0,
                'total_area_sqm' => 0,
                'average_price_per_sqm' => null,
                'median_price_per_sqm' => null,
                'by_land_use' => [],
            ]]);
    }

    public function test_returns_422_for_an_unknown_land_use(): void
    {
        $response = $this->getJson('/api/v1/parcels/statistics?land_use[]=swamp');

        $response->assertUnprocessable()->assertJsonValidationErrors(['land_use.0' => 'The selected land_use.0 is invalid.']);
    }
}
