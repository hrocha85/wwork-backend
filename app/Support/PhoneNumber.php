<?php

namespace App\Support;

class PhoneNumber
{
    public static function digits(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return substr($digits, 0, 32);
    }

    public static function looksLikeEmail(string $value): bool
    {
        return (bool) preg_match('/[A-Za-z@]/', $value);
    }
}
