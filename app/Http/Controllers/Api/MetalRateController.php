<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MetalRateService;
use App\Support\ApiResponse;
use App\Support\MetalRateRealtimeConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MetalRateController extends Controller
{
    public function index(Request $request, MetalRateService $rates): JsonResponse
    {
        $data = $request->validate([
            'metal_type' => ['nullable', 'string', Rule::in(['gold', 'silver'])],
        ]);

        $payload = $rates->getApiRates(
            filled($data['metal_type'] ?? null) ? $data['metal_type'] : null,
        );

        return ApiResponse::success(array_merge($payload, [
            'realtime' => MetalRateRealtimeConfig::make(),
        ]));
    }

    public function realtimeConfig(): JsonResponse
    {
        return ApiResponse::success([
            'realtime' => MetalRateRealtimeConfig::make(),
        ]);
    }
}
