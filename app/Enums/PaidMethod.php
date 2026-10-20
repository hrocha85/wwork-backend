<?php

namespace App\Enums;

/**
 * Como a fatura foi efetivamente paga pelo cliente.
 * Pix só existe no Brasil — validado em MarkInvoicePaid.
 */
enum PaidMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Pix = 'pix';
}
