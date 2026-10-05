<?php

namespace App\Enums;

enum ContactChannel: string
{
    case Whatsapp = 'whatsapp';
    case Sms = 'sms';
    case Call = 'call';
    case Other = 'other';

    /**
     * Lista curta do lançamento. Adicionar um canal é um case novo aqui, sem migration.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
