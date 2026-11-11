<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

class ParcelTileService
{
    private const string VERSION_KEY = 'parcel-tiles:version';

    public function __construct(
        private Cache $cache,
        private ConnectionInterface $database,
    ) {}

    /**
     * Render the Mapbox Vector Tile for an XYZ tile address.
     *
     * @return string The binary tile, or an empty string when no parcel touches the tile.
     */
    public function render(int $zoom, int $column, int $row): string
    {
        $key = sprintf('parcel-tiles:%s:%d/%d/%d', $this->version(), $zoom, $column, $row);

        // Base64 keeps the binary tile intact in text-only cache stores such as "database".
        $encodedTile = $this->cache->remember(
            $key,
            config('webgis.tiles.cache_ttl'),
            fn (): string => base64_encode($this->renderFromDatabase($zoom, $column, $row)),
        );

        return base64_decode($encodedTile);
    }

    /**
     * Invalidate every cached tile. Stale entries are left to expire on their own.
     */
    public function flush(): void
    {
        $this->cache->forever(self::VERSION_KEY, (string) Str::ulid());
    }

    public function isValidAddress(int $zoom, int $column, int $row): bool
    {
        $tilesPerAxis = 2 ** $zoom;

        return $zoom >= config('webgis.tiles.min_zoom')
            && $zoom <= config('webgis.tiles.max_zoom')
            && $column < $tilesPerAxis
            && $row < $tilesPerAxis;
    }

    private function version(): string
    {
        return $this->cache->rememberForever(self::VERSION_KEY, fn (): string => (string) Str::ulid());
    }

    private function renderFromDatabase(int $zoom, int $column, int $row): string
    {
        $result = $this->database->selectOne(<<<'SQL'
            WITH tile AS (
                SELECT ST_TileEnvelope(:zoom, :column, :row) AS envelope
            ),
            features AS (
                SELECT
                    parcels.id,
                    parcels.parcel_number,
                    parcels.land_use,
                    parcels.status,
                    parcels.price_per_sqm,
                    round(parcels.area_sqm)::integer AS area_sqm,
                    ST_AsMVTGeom(parcels.boundary_web_mercator, tile.envelope, :extent, :buffer, true) AS geometry
                FROM parcels, tile
                WHERE parcels.boundary_web_mercator && tile.envelope
            )
            SELECT ST_AsMVT(features, 'parcels', :layer_extent, 'geometry', 'id') AS tile
            FROM features
        SQL, [
            'zoom' => $zoom,
            'column' => $column,
            'row' => $row,
            'extent' => config('webgis.tiles.extent'),
            'buffer' => config('webgis.tiles.buffer'),
            'layer_extent' => config('webgis.tiles.extent'),
        ]);

        // The pgsql driver hands bytea columns back as a stream.
        $tile = $result->tile;

        return is_resource($tile) ? stream_get_contents($tile) : (string) $tile;
    }
}
