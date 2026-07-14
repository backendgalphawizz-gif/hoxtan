<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MetalRateService;
use App\Support\ApiResponse;
use App\Support\MetalRateRealtimeConfig;
use App\Support\WithdrawAssetsBroadcastPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
            'withdraw_assets' => WithdrawAssetsBroadcastPayload::fromRates($ratesPayload),
        ]);
    }

    /**
     * Call immediately after WebSocket subscribe for an instant rates.updated push.
     * Scheduler continues pushing every 30 seconds afterward.
     */
    public function push(MetalRateService $rates): JsonResponse
    {
        // Debounce floods from many clients opening the app at once.
        $shouldBroadcast = Cache::add('metal_rates:push_lock', 1, now()->addSeconds(2));

        if ($shouldBroadcast) {
            $rates->broadcastCurrentRates();
        }

        $ratesPayload = $rates->getApiRates();

        return ApiResponse::success([
            'pushed' => $shouldBroadcast,
            'channel' => (string) config('metal_rates.broadcast_channel', 'metal-rates'),
            'event' => (string) config('metal_rates.broadcast_event', 'rates.updated'),
            'next_broadcast_seconds' => (int) config('metal_rates.broadcast_interval_seconds', 30),
            'rates' => $ratesPayload,
            'withdraw_assets' => WithdrawAssetsBroadcastPayload::fromRates($ratesPayload),
            'instruction' => '1) Connect WS → 2) Subscribe metal-rates → 3) POST /api/v1/rates/push for instant rates.updated → 4) Keep listening; server pushes every 30s.',
        ], $shouldBroadcast
            ? 'Rates pushed to WebSocket subscribers.'
            : 'Rates returned; WebSocket push debounced (another push ran recently).');
    }
}
