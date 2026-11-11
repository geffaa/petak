<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ParcelSearchRequest;
use App\Services\ParcelStatisticsService;
use Illuminate\Http\Resources\Json\JsonResource;

class ParcelStatisticsController extends Controller
{
    /**
     * Summarise the parcels matching the same filters as the search endpoint.
     */
    public function __invoke(ParcelSearchRequest $request, ParcelStatisticsService $statistics): JsonResource
    {
        return new JsonResource($statistics->summarize($request->filters()));
    }
}
