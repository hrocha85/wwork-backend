<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Trial = 'trial';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
    case Complimentary = 'complimentary';
    case PaidOffline = 'paid_offline';
    case Free = 'free';
}
