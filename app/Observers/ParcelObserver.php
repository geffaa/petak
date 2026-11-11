<?php

namespace App\Observers;

use App\Models\Parcel;
use App\Services\ParcelTileService;

class ParcelObserver
{
    public function __construct(private ParcelTileService $tiles) {}

    /**
     * Handle the Parcel "saved" event.
     */
    public function saved(Parcel $parcel): void
    {
        $this->tiles->flush();
    }

    /**
     * Handle the Parcel "deleted" event.
     */
    public function deleted(Parcel $parcel): void
    {
        $this->tiles->flush();
    }
}
