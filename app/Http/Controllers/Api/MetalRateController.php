<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MetalRateService;
use App\Support\ApiResponse;
use App\Support\AssetsBalancePayload;
use App\Support\MetalRateRealtimeConfig;
use App\Support\WithdrawAssetsBroadcastPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

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
            'assets' => $this->assetsForRequest($request, $rates, $payload),
        ]));
    }

    /**
     * WebSocket connection details for live rates.
     * Mobile should connect to `realtime.websocket_url` and listen for rate events —
     * do not poll GET /rates for live prices.
     */
    public function realtimeConfig(Request $request, MetalRateService $rates): JsonResponse
    {
        $ratesPayload = $rates->getApiRates();

        return ApiResponse::success([
            'realtime' => MetalRateRealtimeConfig::make(),
            // One-time bootstrap only (optional). Prefer WebSocket event after connect.
            'rates' => $ratesPayload,
            'withdraw_assets' => WithdrawAssetsBroadcastPayload::fromRates($ratesPayload),
            'assets' => $this->assetsForRequest($request, $rates, $ratesPayload),
        ]);
    }

    /**
     * Call immediately after WebSocket subscribe for an instant rates.updated push.
     * Send Bearer token so response includes wallet + gold/silver amounts.
     * Scheduler continues pushing every 30 seconds afterward.
     */
    public function push(Request $request, MetalRateService $rates): JsonResponse
    {
        // Debounce floods from many clients opening the app at once.
        $shouldBroadcast = Cache::add('metal_rates:push_lock', 1, now()->addSeconds(2));

        if ($shouldBroadcast) {
            $rates->broadcastCurrentRates();
        }

        $ratesPayload = $rates->getApiRates();
        $assets = $this->assetsForRequest($request, $rates, $ratesPayload);
        $user = $this->resolveUser($request);

        return ApiResponse::success([
            'pushed' => $shouldBroadcast,
            'channel' => (string) config('metal_rates.broadcast_channel', 'metal-rates'),
            'event' => (string) config('metal_rates.broadcast_event', 'rates.updated'),
            'next_broadcast_seconds' => (int) config('metal_rates.broadcast_interval_seconds', 30),
            'rates' => $ratesPayload,
            'withdraw_assets' => WithdrawAssetsBroadcastPayload::fromRates($ratesPayload),
            'assets' => $assets,
            'wallet_balance' => $assets['wallet_balance'] ?? null,
            'wallet_balance_display' => $assets['wallet_balance_display'] ?? null,
            'total_assets_balance' => $assets['total_assets_balance'] ?? null,
            'total_assets_balance_display' => $assets['total_assets_balance_display'] ?? null,
            'authenticated' => $user !== null,
            'instruction' => $user
                ? 'Use data.assets (wallet + gold/silver amounts). On WebSocket rates.updated: keep grams/wallet_balance, refresh rate_per_gram, recalculate wallet_amount/value.'
                : 'Send Authorization: Bearer {token} with this call to receive wallet_balance + gold/silver wallet amounts in data.assets.',
        ], $shouldBroadcast
            ? 'Rates pushed to WebSocket subscribers.'
            : 'Rates returned; WebSocket push debounced (another push ran recently).');
    }

    /**
     * @param  array<string, mixed>  $ratesPayload
     * @return array<string, mixed>
     */
    protected function assetsForRequest(Request $request, MetalRateService $rates, array $ratesPayload): array
    {
        $user = $this->resolveUser($request);

        if ($user instanceof User) {
            return AssetsBalancePayload::make($user->fresh(), $rates);
        }

        return AssetsBalancePayload::broadcastShellFromRates($ratesPayload);
    }

    protected function resolveUser(Request $request): ?User
    {
        $user = $request->user();
        if ($user instanceof User) {
            return $user;
        }

        $plain = $request->bearerToken();
        if (! filled($plain)) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($plain);
        $tokenable = $accessToken?->tokenable;

        return $tokenable instanceof User ? $tokenable : null;
    }
}
