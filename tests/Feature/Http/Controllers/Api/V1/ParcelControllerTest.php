<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\LandUse;
use App\Enums\ParcelStatus;
use App\Models\Parcel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ParcelControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_parcels_intersecting_the_bounding_box(): void
    {
        $inside = Parcel::factory()->squareAt(110.4000, -7.7400)->create();
        Parcel::factory()->squareAt(110.4200, -7.7400)->create();

        $response = $this->getJson('/api/v1/parcels?bbox=110.399,-7.741,110.401,-7.739');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inside->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_lists_only_parcels_within_the_search_radius_with_their_distance(): void
    {
        // A 20 m square centred 0.001° (about 110.6 m) north of the search centre: its near edge is about 100.6 m away.
        $near = Parcel::factory()->squareAt(110.4000, -7.7390)->create();
        Parcel::factory()->squareAt(110.4000, -7.7300)->create();

        $response = $this->getJson('/api/v1/parcels?near=110.4000,-7.7400&radius=300');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $near->id);
        $this->assertEqualsWithDelta(100.6, $response->json('data.0.distance_m'), 0.5);
    }

    public function test_orders_parcels_by_distance_when_sorting_by_nearest(): void
    {
        $farther = Parcel::factory()->squareAt(110.4000, -7.7380)->create();
        $nearer = Parcel::factory()->squareAt(110.4000, -7.7395)->create();

        $response = $this->getJson('/api/v1/parcels?near=110.4000,-7.7400&radius=1000&sort=nearest');

        $response->assertOk();
        $this->assertSame([$nearer->id, $farther->id], $response->json('data.*.id'));
    }

    public function test_filters_parcels_by_land_use_and_status(): void
    {
        $match = Parcel::factory()->landUse(LandUse::Commercial)->status(ParcelStatus::Available)->create();
        Parcel::factory()->landUse(LandUse::Commercial)->status(ParcelStatus::Sold)->create();
        Parcel::factory()->landUse(LandUse::Residential)->status(ParcelStatus::Available)->create();

        $response = $this->getJson('/api/v1/parcels?land_use[]=commercial&status[]=available');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('data.0.land_use', 'commercial')
            ->assertJsonPath('data.0.status', 'available');
    }

    public function test_filters_parcels_by_price_per_square_metre_range(): void
    {
        Parcel::factory()->pricedAt(2_000_000)->create();
        $match = Parcel::factory()->pricedAt(5_000_000)->create();
        Parcel::factory()->pricedAt(9_000_000)->create();

        $response = $this->getJson('/api/v1/parcels?min_price_per_sqm=3000000&max_price_per_sqm=6000000');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_filters_parcels_by_area_range(): void
    {
        Parcel::factory()->squareAt(110.4000, -7.7400, sideMeters: 10)->create();
        $match = Parcel::factory()->squareAt(110.4010, -7.7400, sideMeters: 20)->create();
        Parcel::factory()->squareAt(110.4020, -7.7400, sideMeters: 40)->create();

        $response = $this->getJson('/api/v1/parcels?min_area_sqm=200&max_area_sqm=800');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_sorts_parcels_by_price_descending(): void
    {
        $cheap = Parcel::factory()->pricedAt(2_000_000)->create();
        $expensive = Parcel::factory()->pricedAt(9_000_000)->create();

        $response = $this->getJson('/api/v1/parcels?sort=price_desc');

        $response->assertOk();
        $this->assertSame([$expensive->id, $cheap->id], $response->json('data.*.id'));
    }

    public function test_reports_area_and_total_price_derived_from_the_boundary(): void
    {
        Parcel::factory()->squareAt(110.4000, -7.7400, sideMeters: 20)->pricedAt(5_000_000)->create();

        $response = $this->getJson('/api/v1/parcels');

        $response->assertOk()->assertJsonPath('data.0.price_per_sqm', 5_000_000);
        $this->assertEqualsWithDelta(400.0, $response->json('data.0.area_sqm'), 2.0);
        $this->assertEqualsWithDelta(2_000_000_000, $response->json('data.0.total_price'), 10_000_000);
        $this->assertEqualsWithDelta(110.4000, $response->json('data.0.label_point.0'), 0.0001);
        $this->assertEqualsWithDelta(-7.7400, $response->json('data.0.label_point.1'), 0.0001);
    }

    public function test_limits_the_page_size_to_the_requested_amount(): void
    {
        Parcel::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/parcels?per_page=2');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function invalidSearches(): array
    {
        return [
            'bounding box with three coordinates' => ['bbox=110.39,-7.75,110.41', 'bbox', 'The bounding box field must contain 4 items.'],
            'bounding box latitude out of range' => ['bbox=110.39,-97.75,110.41,-7.73', 'bbox.1', 'The bbox.1 field must be between -90 and 90.'],
            'unknown land use' => ['land_use[]=swamp', 'land_use.0', 'The selected land_use.0 is invalid.'],
            'unknown status' => ['status[]=haunted', 'status.0', 'The selected status.0 is invalid.'],
            'radius without a centre' => ['radius=500', 'near', 'The search centre field is required when search radius is present.'],
            'centre without a radius' => ['near=110.40,-7.74', 'radius', 'The search radius field is required when search centre is present.'],
            'radius above the limit' => ['near=110.40,-7.74&radius=5001', 'radius', 'The search radius field must not be greater than 5000.'],
            'nearest sort without a centre' => ['sort=nearest', 'near', 'The search centre field is required when sort is nearest.'],
            'unknown sort' => ['sort=random', 'sort', 'The selected sort is invalid.'],
            'page size above the limit' => ['per_page=51', 'per_page', 'The per page field must not be greater than 50.'],
            'negative minimum price' => ['min_price_per_sqm=-1', 'min_price_per_sqm', 'The min price per sqm field must be at least 0.'],
        ];
    }

    #[DataProvider('invalidSearches')]
    public function test_returns_422_for_an_invalid_search(string $queryString, string $field, string $message): void
    {
        $response = $this->getJson('/api/v1/parcels?'.$queryString);

        $response->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);
    }

    public function test_shows_a_parcel_as_a_geojson_feature(): void
    {
        $parcel = Parcel::factory()
            ->squareAt(110.4000, -7.7400)
            ->landUse(LandUse::MixedUse)
            ->status(ParcelStatus::Reserved)
            ->pricedAt(7_500_000)
            ->create(['parcel_number' => 'C05-012']);

        $response = $this->getJson("/api/v1/parcels/{$parcel->id}");

        $response
            ->assertOk()
            ->assertJsonPath('type', 'Feature')
            ->assertJsonPath('id', $parcel->id)
            ->assertJsonPath('geometry.type', 'Polygon')
            ->assertJsonCount(5, 'geometry.coordinates.0')
            ->assertJsonPath('properties.parcel_number', 'C05-012')
            ->assertJsonPath('properties.land_use', 'mixed_use')
            ->assertJsonPath('properties.status', 'reserved')
            ->assertJsonPath('properties.price_per_sqm', 7_500_000)
            ->assertJsonMissingPath('data');
        $this->assertEqualsWithDelta(110.39991, $response->json('bbox.0'), 0.00001);
        $this->assertEqualsWithDelta(-7.73991, $response->json('bbox.3'), 0.00001);
    }

    public function test_returns_404_for_a_parcel_that_does_not_exist(): void
    {
        $response = $this->getJson('/api/v1/parcels/999999');

        $response->assertNotFound();
    }

    public function test_returns_404_for_a_non_numeric_parcel_id(): void
    {
        $response = $this->getJson('/api/v1/parcels/1;DROP TABLE parcels');

        $response->assertNotFound();
    }

    public function test_treats_an_injection_attempt_in_the_bounding_box_as_invalid_input(): void
    {
        Parcel::factory()->create();

        $response = $this->getJson('/api/v1/parcels?bbox='.urlencode('110.39,-7.75,110.41,-7.73); DROP TABLE parcels;--'));

        $response->assertUnprocessable()->assertJsonValidationErrors(['bbox.3']);
        $this->assertDatabaseCount('parcels', 1);
    }
}
