<?php

namespace App\Enums;

enum VisitStatus: string
{
    case Offered = 'offered';
    case Todo = 'todo';
    case EnRoute = 'en_route';
    case CheckedIn = 'checked_in';
    case Done = 'done';
}
