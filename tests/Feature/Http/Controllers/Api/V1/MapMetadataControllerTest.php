<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Parcel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapMetadataControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_describes_the_tile_source_and_category_legends(): void
    {
        $response = $this->getJson('/api/v1/map');

        $response
            ->assertOk()
            ->assertJsonPath('data.tiles.url', 'http://localhost/api/v1/parcels/tiles/{z}/{x}/{y}.mvt')
            ->assertJsonPath('data.tiles.layer', 'parcels')
            ->assertJsonPath('data.tiles.min_zoom', 12)
            ->assertJsonPath('data.tiles.max_zoom', 16)
            ->assertJsonPath('data.land_uses.0', ['value' => 'residential', 'label' => 'Permukiman', 'color' => '#F2C14E'])
            ->assertJsonCount(5, 'data.land_uses')
            ->assertJsonPath('data.statuses.0', ['value' => 'available', 'label' => 'Tersedia', 'color' => '#2E9E6B'])
            ->assertJsonCount(3, 'data.statuses');
    }

    public function test_reports_the_extent_and_price_breaks_of_the_stored_parcels(): void
    {
        Parcel::factory()->squareAt(110.4000, -7.7400)->pricedAt(2_000_000)->create();
        Parcel::factory()->squareAt(110.4100, -7.7300)->pricedAt(12_000_000)->create();

        $response = $this->getJson('/api/v1/map');

        $response
            ->assertOk()
            ->assertJsonPath('data.price_per_sqm_breaks', [2_000_000, 4_000_000, 6_000_000, 8_000_000, 10_000_000, 12_000_000]);
        $this->assertEqualsWithDelta(110.39991, $response->json('data.extent.0'), 0.00001);
        $this->assertEqualsWithDelta(-7.74009, $response->json('data.extent.1'), 0.00001);
        $this->assertEqualsWithDelta(110.41009, $response->json('data.extent.2'), 0.00001);
        $this->assertEqualsWithDelta(-7.72991, $response->json('data.extent.3'), 0.00001);
    }

    public function test_reports_no_extent_or_price_breaks_when_there_are_no_parcels(): void
    {
        $response = $this->getJson('/api/v1/map');

        $response
            ->assertOk()
            ->assertJsonPath('data.extent', null)
            ->assertJsonPath('data.price_per_sqm_breaks', []);
    }
}
