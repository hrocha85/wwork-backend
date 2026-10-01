<?php

namespace App\Enums;

enum PayBy: string
{
    case Link = 'link';
    case InPerson = 'in_person';
}