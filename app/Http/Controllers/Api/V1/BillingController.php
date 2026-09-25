<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Billing\MarkPayoutPaid;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PeriodRequest;
use App\Http\Resources\Api\V1\BillingResource;
use App\Models\Payout;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Http\JsonResponse;

class BillingController extends Controller
{
    public function show(PeriodRequest $request): JsonResponse
    {
        $period = $request->validated('period') ?: 'week';

        return response()->json(BillingResource::show($period));
    }

    public function pay(int $payout, MarkPayoutPaid $mark): JsonResponse
    {
        $model = Payout::query()->find($payout);

        if ($model === null) {
            throw new ApiException(ErrorCodes::NOT_FOUND, 404);
        }

        $saved = $mark($model);
        $saved->loadMissing('agency');

        return response()->json([
            'id' => $saved->id,
            'paid' => $saved->paid,
            'paid_at' => $saved->paid_at?->timezone($saved->agency->timezone)->toIso8601String(),
        ]);
    }
}
