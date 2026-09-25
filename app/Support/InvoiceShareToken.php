<?php

namespace App\Support;

class InvoiceShareToken
{
    public static function issue(int $invoiceId): string
    {
        $body = $invoiceId.'.'.now()->addDays(30)->getTimestamp();
        $sig = hash_hmac('sha256', $body, (string) config('app.key'));

        return rtrim(strtr(base64_encode($body.'.'.$sig), '+/', '-_'), '=');
    }

    public static function invoiceId(string $token): int
    {
        $padded = strtr($token, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        $raw = base64_decode($padded, true);

        if ($raw === false) {
            throw new ApiException(ErrorCodes::INVOICE_SHARE_EXPIRED, 404);
        }

        $parts = explode('.', $raw);

        if (count($parts) !== 3) {
            throw new ApiException(ErrorCodes::INVOICE_SHARE_EXPIRED, 404);
        }

        [$id, $exp, $sig] = $parts;
        $expected = hash_hmac('sha256', $id.'.'.$exp, (string) config('app.key'));

        if (! hash_equals($expected, $sig) || (int) $exp < now()->getTimestamp()) {
            throw new ApiException(ErrorCodes::INVOICE_SHARE_EXPIRED, 404);
        }

        return (int) $id;
    }
}
