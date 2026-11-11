<?php

namespace App\Enums;

enum LandUse: string
{
    case Residential = 'residential';
    case Commercial = 'commercial';
    case MixedUse = 'mixed_use';
    case Industrial = 'industrial';
    case Agricultural = 'agricultural';

    public function label(): string
    {
        return match ($this) {
            self::Residential => 'Permukiman',
            self::Commercial => 'Komersial',
            self::MixedUse => 'Campuran',
            self::Industrial => 'Industri & Gudang',
            self::Agricultural => 'Pertanian',
        };
    }

    /**
     * Hex colour used for this land use on the map and in legends.
     */
    public function color(): string
    {
        return match ($this) {
            self::Residential => '#F2C14E',
            self::Commercial => '#E4572E',
            self::MixedUse => '#A06CD5',
            self::Industrial => '#5B7C99',
            self::Agricultural => '#3FA773',
        };
    }
}
