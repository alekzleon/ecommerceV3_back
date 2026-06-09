<?php

namespace App\Enums;

enum CartItemStatus: string
{
    case ACTIVE = 'active';
    case REMOVED = 'removed';
    case UNAVAILABLE = 'unavailable';
    case PRICE_CHANGED = 'price_changed';
    case RESTRICTED = 'restricted';
}