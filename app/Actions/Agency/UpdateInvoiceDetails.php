<?php

namespace App\Actions\Agency;

use App\Enums\MembershipRole;
use App\Enums\PaymentMethod;
use App\Models\Agency;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;

class UpdateInvoiceDetails
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(array $input): Agency
    {
        $membership = AgencyContext::membership();

        if ($membership->role !== MembershipRole::Owner) {
            throw new ApiException(ErrorCodes::AGENCY_NOT_OWNER, 403);
        }

        $agency = $membership->agency;

        if (array_key_exists('currency', $input) && is_string($input['currency'])) {
            $currency = strtoupper(trim($input['currency']));
            if (! in_array($currency, ['BRL', 'GBP', 'EUR', 'USD', 'PLN', 'RON'], true)) {
                throw new ApiException('agency.currency_invalid', 422);
            }
            $agency->currency = $currency;
            $agency->invoice_region = Agency::regionForCurrency($currency);
        }

        $hasInvoiceFields = array_key_exists('legal_address', $input)
            || array_key_exists('phone', $input)
            || array_key_exists('payment_method', $input)
            || array_key_exists('payment_details', $input)
            || array_key_exists('payment_link', $input)
            || array_key_exists('vat_registered', $input)
            || array_key_exists('tax_id', $input);

        if ($hasInvoiceFields) {
            $phone = $this->blank($input['phone'] ?? null);
            $method = $this->blank($input['payment_method'] ?? null);
            $details = $this->blank($input['payment_details'] ?? null);
            $payLink = $this->blank($input['payment_link'] ?? null);
            if ($payLink !== null && filter_var($payLink, FILTER_VALIDATE_URL) === false) {
                throw new ApiException('agency.payment_link_invalid', 422);
            }
            $address = $this->blank($input['legal_address'] ?? null);
            $taxId = $this->blank($input['tax_id'] ?? null);
            $vat = filter_var($input['vat_registered'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($phone !== null && ! preg_match('/^[0-9+\s()-]{8,20}$/', $phone)) {
                throw new ApiException(ErrorCodes::AGENCY_PHONE_INVALID, 422);
            }

            if ($method !== null && PaymentMethod::tryFrom($method) === null) {
                throw new ApiException(ErrorCodes::AGENCY_PAYMENT_METHOD_INVALID, 422);
            }

            if ($address !== null && (mb_strlen($address) < 2 || mb_strlen($address) > 200)) {
                throw new ApiException(ErrorCodes::AGENCY_ADDRESS_INVALID, 422);
            }

            if ($details !== null && mb_strlen($details) > 300) {
                throw new ApiException(ErrorCodes::AGENCY_PAYMENT_DETAILS_INVALID, 422);
            }

            if ($vat && $taxId === null) {
                throw new ApiException(ErrorCodes::AGENCY_TAX_ID_REQUIRED, 422);
            }

            $agency->forceFill([
                'legal_address' => $address,
                'phone' => $phone,
                'payment_method' => $method,
                'payment_details' => $details,
                'payment_link' => $payLink,
                'vat_registered' => $vat,
                'tax_id' => $vat ? $taxId : null,
            ]);
        }

        $agency->save();

        RecordActivity::add($agency->id, AgencyContext::user()->id, 'agency.invoice_details');

        return $agency;
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
