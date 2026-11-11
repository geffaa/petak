<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Parcel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ParcelTileControllerTest extends TestCase
{
    use RefreshDatabase;

    /** The zoom 14 tile that contains longitude 110.4085, latitude -7.7420. */
    private const string TILE_WITH_PARCEL = '/api/v1/parcels/tiles/14/13216/8545.mvt';

    private const string TILE_WITHOUT_PARCEL = '/api/v1/parcels/tiles/14/0/0.mvt';

    public function test_returns_a_cacheable_vector_tile_for_a_tile_containing_a_parcel(): void
    {
        Parcel::factory()->squareAt(110.4085, -7.7420)->create(['parcel_number' => 'E05-001']);

        $response = $this->get(self::TILE_WITH_PARCEL);

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.mapbox-vector-tile')
            ->assertHeader('Cache-Control', 'max-age=3600, public')
            ->assertHeader('ETag');
        // Vector tiles are protobuf; string values such as the layer name and attributes are stored verbatim.
        $this->assertStringContainsString('parcels', $response->getContent());
        $this->assertStringContainsString('E05-001', $response->getContent());
    }

    public function test_returns_204_for_a_tile_without_parcels(): void
    {
        Parcel::factory()->squareAt(110.4085, -7.7420)->create();

        $response = $this->get(self::TILE_WITHOUT_PARCEL);

        $response->assertNoContent();
    }

    public function test_returns_304_when_the_client_already_has_the_current_tile(): void
    {
        Parcel::factory()->squareAt(110.4085, -7.7420)->create();
        $etag = $this->get(self::TILE_WITH_PARCEL)->headers->get('ETag');

        $response = $this->get(self::TILE_WITH_PARCEL, ['If-None-Match' => $etag]);

        $response->assertStatus(304);
        $this->assertSame('', $response->getContent());
    }

    public function test_serves_a_fresh_tile_after_a_parcel_is_created(): void
    {
        $this->get(self::TILE_WITH_PARCEL)->assertNoContent();
        Parcel::factory()->squareAt(110.4085, -7.7420)->create();

        $response = $this->get(self::TILE_WITH_PARCEL);

        $response->assertOk();
    }

    public function test_serves_a_fresh_tile_after_a_parcel_is_deleted(): void
    {
        $parcel = Parcel::factory()->squareAt(110.4085, -7.7420)->create();
        $this->get(self::TILE_WITH_PARCEL)->assertOk();
        $parcel->delete();

        $response = $this->get(self::TILE_WITH_PARCEL);

        $response->assertNoContent();
    }

    #[TestWith(['/api/v1/parcels/tiles/11/1652/1068.mvt'], 'zoom below the minimum')]
    #[TestWith(['/api/v1/parcels/tiles/17/105734/68363.mvt'], 'zoom above the maximum')]
    #[TestWith(['/api/v1/parcels/tiles/14/16384/8545.mvt'], 'column outside the tile grid')]
    #[TestWith(['/api/v1/parcels/tiles/14/13216/16384.mvt'], 'row outside the tile grid')]
    #[TestWith(['/api/v1/parcels/tiles/14/13216/-1.mvt'], 'negative row')]
    #[TestWith(['/api/v1/parcels/tiles/14/13216/8545.png'], 'unsupported format')]
    public function test_returns_404_for_an_invalid_tile_address(string $url): void
    {
        $response = $this->get($url);

        $response->assertNotFound();
    }
}
