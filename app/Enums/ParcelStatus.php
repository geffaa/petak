<?php

namespace App\Enums;

enum ParcelStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Sold = 'sold';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Reserved => 'Dipesan',
            self::Sold => 'Terjual',
        };
    }

    /**
     * Hex colour used for this status on the map and in legends.
     */
    public function color(): string
    {
        return match ($this) {
            self::Available => '#2E9E6B',
            self::Reserved => '#E0A126',
            self::Sold => '#9AA3AE',
        };
    }
}
