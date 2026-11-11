<?php

namespace App\Enums;

enum ParcelSort: string
{
    case PriceAscending = 'price_asc';
    case PriceDescending = 'price_desc';
    case AreaAscending = 'area_asc';
    case AreaDescending = 'area_desc';
    case Nearest = 'nearest';
}
