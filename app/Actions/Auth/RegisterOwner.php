<?php

namespace App\Actions\Auth;

use App\Enums\Locale;
use App\Enums\Trade;
use App\Models\User;
use App\Support\ApiException;
use App\Support\ErrorCodes;

class RegisterOwner
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(array $data): never
    {
        if (($data['terms_accepted'] ?? null) !== true) {
            throw new ApiException(ErrorCodes::REGISTER_TERMS_REQUIRED, 422);
        }

        if (Trade::tryFrom((string) ($data['trade'] ?? '')) === null) {
            throw new ApiException(ErrorCodes::REGISTER_INVALID_TRADE, 422);
        }

        if (Locale::tryFrom((string) ($data['locale'] ?? '')) === null) {
            throw new ApiException(ErrorCodes::ME_INVALID_LOCALE, 422);
        }

        if (User::query()->where('email', $data['email'])->exists()) {
            throw new ApiException(ErrorCodes::REGISTER_EMAIL_TAKEN, 422);
        }

        // TODO fazer 14: com STRIPE_SECRET de teste, criar customer + subscription
        // wwork_basic (metadata country + trade) e só então gravar agência, dono,
        // membership e activity. Sem chave não se cria conta e não se finge paid.
        throw new ApiException(ErrorCodes::REGISTER_PAYMENT_UNAVAILABLE, 503);
    }
}
