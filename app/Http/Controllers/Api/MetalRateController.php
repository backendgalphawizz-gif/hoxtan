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

    /**
     * WebSocket connection details for live rates.
     * Mobile should connect to `realtime.websocket_url` and listen for rate events —
     * do not poll GET /rates for live prices.
     */
    public function realtimeConfig(MetalRateService $rates): JsonResponse
    {
        $ratesPayload = $rates->getApiRates();

        return ApiResponse::success([
            'realtime' => MetalRateRealtimeConfig::make(),
            // One-time bootstrap only (optional). Prefer WebSocket event after connect.
            'rates' => $ratesPayload,
            'withdraw_assets' => \App\Support\WithdrawAssetsBroadcastPayload::fromRates($ratesPayload),
        ]);
    }
}
