<?php

namespace App\Enums;

enum AnnualDiscount: string
{
    case None = 'none';
    case TwoMonthsFree = 'two_months_free';
    case TwentyPercent = 'twenty_percent';
}
