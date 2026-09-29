<?php

namespace App\Enums;

enum BookingRequestStatus: string
{
    case Pending = 'pending';
    case Quoted = 'quoted';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
