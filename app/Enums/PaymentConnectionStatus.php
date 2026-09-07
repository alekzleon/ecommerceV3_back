<?php

namespace App\Enums;

enum PaymentConnectionStatus: string
{
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Error = 'error';
    case ReauthorizationRequired = 'reauthorization_required';
}
