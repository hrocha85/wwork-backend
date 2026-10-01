<?php

namespace App\Actions\Invoices;

use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\Visit;
use App\Policies\InvoicePolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;

class GetPendingInvoices
{
    /**
     * @return array<int, array{
     *     client: array{id: int, name: string, address: string},
     *     visits: array<int, array{id: int, date: string, description: string, price_pence: int, has_photo: bool}>,
     *     total_pence: int,
     *     checklist: array{photos_missing: array<int, int>, details_missing: array<int, string>, price_missing: bool}
     * }>
     */
    public function __invoke(): array
    {
        $actor = AgencyContext::user();

        if (! app(InvoicePolicy::class)->create($actor)) {
            throw new ApiException(ErrorCodes::INVOICE_FORBIDDEN, 403);
        }

        $membership = AgencyContext::membership();

        $visits = Visit::query()
            ->where('agency_id', $membership->agency_id)
            ->where('status', VisitStatus::Done)
            ->whereDoesntHave('invoiceLines')
            ->with(['client', 'photos'])
            ->orderBy('client_id')
            ->orderBy('service_date')
            ->orderBy('id')
            ->get();

        $grouped = $visits->groupBy('client_id');

        $result = [];
        foreach ($grouped as $clientId => $clientVisits) {
            $client = $clientVisits->first()->client;

            $photosMissing = [];
            $detailsMissing = [];
            $priceMissing = false;
            $totalPence = 0;

            $visitData = [];
            foreach ($clientVisits as $visit) {
                $hasPhoto = $visit->photos->count() > 0;
                if (! $hasPhoto) {
                    $photosMissing[] = $visit->id;
                }

                $visitTotal = (int) $visit->price_pence;
                if ($visitTotal < 1) {
                    $priceMissing = true;
                    $detailsMissing[] = "price:{$visit->id}";
                }

                if (! $visit->description || trim($visit->description) === '') {
                    $detailsMissing[] = "description:{$visit->id}";
                }

                $totalPence += $visitTotal;

                $visitData[] = [
                    'id' => $visit->id,
                    'date' => $visit->service_date->toDateString(),
                    'description' => $visit->description,
                    'price_pence' => $visitTotal,
                    'has_photo' => $hasPhoto,
                ];
            }

            if (! $client->address || trim($client->address) === '') {
                $detailsMissing[] = 'client_address';
            }

            $result[] = [
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'address' => $client->address ?? '',
                ],
                'visits' => $visitData,
                'total_pence' => $totalPence,
                'checklist' => [
                    'photos_missing' => $photosMissing,
                    'details_missing' => $detailsMissing,
                    'price_missing' => $priceMissing,
                ],
            ];
        }

        return $result;
    }
}