<?php

namespace App\Enums;

enum CartStatus: string
{
    case ACTIVE = 'active';
    case ABANDONED = 'abandoned';
    case CONVERTED = 'converted';
    case ARCHIVED = 'archived';
}