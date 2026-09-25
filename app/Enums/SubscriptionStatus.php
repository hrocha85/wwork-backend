<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
    case Complimentary = 'complimentary';
    case PaidOffline = 'paid_offline';
}
