<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Basemap
    |--------------------------------------------------------------------------
    |
    | MapLibre style document drawn underneath the parcel layer. The default
    | is OpenFreeMap, which needs no API key.
    |
    */

    'basemap_style_url' => env('WEBGIS_BASEMAP_STYLE_URL', 'https://tiles.openfreemap.org/styles/positron'),

    /*
    |--------------------------------------------------------------------------
    | Vector Tiles
    |--------------------------------------------------------------------------
    |
    | Parcels are served as Mapbox Vector Tiles rendered by PostGIS. Tiles are
    | only generated between the zoom levels below; the client overzooms past
    | the maximum. Rendered tiles are cached for "cache_ttl" seconds.
    |
    */

    'tiles' => [
        'min_zoom' => 12,
        'max_zoom' => 16,
        'extent' => 4096,
        'buffer' => 64,
        'cache_ttl' => (int) env('WEBGIS_TILE_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Limits
    |--------------------------------------------------------------------------
    */

    'search' => [
        'default_per_page' => 20,
        'max_per_page' => 50,
        'max_radius_meters' => 5000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo Data
    |--------------------------------------------------------------------------
    |
    | Where the seeder lays out its synthetic neighbourhood: the centre as
    | [longitude, latitude] and the street grid's rotation from north.
    |
    */

    'demo' => [
        'center' => [110.4085, -7.7420],
        'rotation_degrees' => 12.0,
        'seed' => 20261007,
    ],

];
