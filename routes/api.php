<?php

use App\Http\Controllers\Api\V1\MapMetadataController;
use App\Http\Controllers\Api\V1\ParcelController;
use App\Http\Controllers\Api\V1\ParcelStatisticsController;
use App\Http\Controllers\Api\V1\ParcelTileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::middleware('throttle:120,1')->group(function () {
        Route::get('map', MapMetadataController::class)->name('map');
        Route::get('parcels/statistics', ParcelStatisticsController::class)->name('parcels.statistics');
        Route::get('parcels', [ParcelController::class, 'index'])->name('parcels.index');
        Route::get('parcels/{parcel}', [ParcelController::class, 'show'])
            ->where('parcel', '\d{1,18}')
            ->name('parcels.show');
    });

    // Tiles are requested in bursts while panning, so they rely on HTTP caching instead of a rate limit.
    Route::get('parcels/tiles/{zoom}/{column}/{row}.mvt', ParcelTileController::class)
        ->where(['zoom' => '\d{1,2}', 'column' => '\d{1,7}', 'row' => '\d{1,7}'])
        ->name('parcels.tiles');
});
