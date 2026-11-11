<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ParcelTileService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ParcelTileController extends Controller
{
    /**
     * Serve one parcel vector tile with HTTP caching.
     */
    public function __invoke(Request $request, ParcelTileService $tiles, int $zoom, int $column, int $row): Response
    {
        abort_unless($tiles->isValidAddress($zoom, $column, $row), Response::HTTP_NOT_FOUND);

        $tile = $tiles->render($zoom, $column, $row);

        $response = $tile === ''
            ? response()->noContent()
            : response($tile, headers: ['Content-Type' => 'application/vnd.mapbox-vector-tile']);

        $response
            ->setPublic()
            ->setMaxAge(config('webgis.tiles.cache_ttl'))
            ->setEtag(md5($tile));

        $response->isNotModified($request);

        return $response;
    }
}
