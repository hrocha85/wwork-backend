<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Telemetry\RecordInstallClick;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InstallClickRequest;
use Illuminate\Http\JsonResponse;

class TelemetryController extends Controller
{
    public function installClick(InstallClickRequest $request, RecordInstallClick $recordInstallClick): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();

        $recordInstallClick(
            platform: $request->validated('platform'),
            timestamp: $request->validated('timestamp'),
            user: $user,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'success' => true,
        ], 201);
    }
}
