<?php

namespace App\Enums;

enum PlanCode: string
{
    case Basic = 'wwork_basic';
    case Pro = 'wwork_pro';
    case Business = 'wwork_business';

    public function maxSeats(): int
    {
        return match ($this) {
            self::Basic => 3,
            self::Pro => 6,
            self::Business => 10,
        };
    }
}
