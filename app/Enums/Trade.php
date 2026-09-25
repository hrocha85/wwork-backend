<?php

namespace App\Enums;

enum Trade: string
{
    case Cleaning = 'cleaning';
    case Lawn = 'lawn';
    case Pool = 'pool';
    case Garden = 'garden';
    case Other = 'other';
}
