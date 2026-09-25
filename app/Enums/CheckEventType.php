<?php

namespace App\Enums;

enum CheckEventType: string
{
    case EnRoute = 'en_route';
    case CheckIn = 'check_in';
    case CheckOut = 'check_out';
}
