<?php

namespace App\Actions\Auth;

use App\Enums\MembershipRole;
use App\Enums\Trade;
use App\Models\User;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;

class CompleteFirstAccess
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(array $input): User
    {
        $membership = AgencyContext::membership();

        if ($membership->role !== MembershipRole::Owner) {
            throw new ApiException(ErrorCodes::AGENCY_NOT_OWNER, 403);
        }

        $name = trim((string) ($input['name'] ?? ''));
        $trade = Trade::tryFrom((string) ($input['trade'] ?? ''));
        $detail = $this->blank($input['trade_detail'] ?? null);
        $address = $this->blank($input['legal_address'] ?? null);

        if (mb_strlen($name) < 2 || mb_strlen($name) > 80 || $trade === null) {
            throw new ApiException(ErrorCodes::ONBOARDING_INVALID, 422);
        }

        if ($trade === Trade::Other && ($detail === null || mb_strlen($detail) < 2 || mb_strlen($detail) > 80)) {
            throw new ApiException(ErrorCodes::ONBOARDING_INVALID, 422);
        }

        if ($address !== null && (mb_strlen($address) < 2 || mb_strlen($address) > 200)) {
            throw new ApiException(ErrorCodes::AGENCY_ADDRESS_INVALID, 422);
        }

        $agency = $membership->agency;
        $agency->forceFill([
            'name' => $name,
            'trade' => $trade,
            'trade_detail' => $trade === Trade::Other ? $detail : null,
            'legal_address' => $address,
        ])->save();

        $user = AgencyContext::user();
        if ($user->first_access_at === null) {
            User::query()->whereKey($user->id)->update(['first_access_at' => now()]);
        }

        RecordActivity::add($agency->id, $user->id, 'auth.first_access');

        return $user->fresh(['membership.agency.subscription']);
    }

    private function blank(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
