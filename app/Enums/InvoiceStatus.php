<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case ToSend = 'to_send';
    case Sent = 'sent';
    case Paid = 'paid';
}
