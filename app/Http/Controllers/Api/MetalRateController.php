<?php

namespace App\Http\Controllers\Api;

use App\Events\UserAssetsUpdated;
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
            'withdraw_assets' => $this->withdrawAssetsForRequest($request, $payload),
        ]));
    }

    public function realtimeConfig(Request $request, MetalRateService $rates): JsonResponse
    {
        $ratesPayload = $rates->getApiRates();

        return ApiResponse::success([
            'realtime' => MetalRateRealtimeConfig::make(),
            'rates' => $ratesPayload,
            'withdraw_assets' => $this->withdrawAssetsForRequest($request, $ratesPayload),
            'assets' => $this->assetsForRequest($request, $rates, $ratesPayload),
        ]);
    }

    /**
     * Instant rates + (with Bearer token) full gold/silver wallet + withdrawable assets.
     */
    public function push(Request $request, MetalRateService $rates): JsonResponse
    {
        $shouldBroadcast = Cache::add('metal_rates:push_lock', 1, now()->addSeconds(2));

        if ($shouldBroadcast) {
            $rates->broadcastCurrentRates();
        }

        $ratesPayload = $rates->getApiRates();
        $user = $this->resolveUser($request);
        $assets = $this->assetsForRequest($request, $rates, $ratesPayload);
        $withdrawAssets = $this->withdrawAssetsForRequest($request, $ratesPayload);

        if ($user instanceof User) {
            UserAssetsUpdated::dispatch(
                (int) $user->id,
                array_merge($assets, ['withdraw_assets' => $withdrawAssets]),
                'rates_push',
            );
        }

        return ApiResponse::success([
            'pushed' => $shouldBroadcast,
            'channel' => (string) config('metal_rates.broadcast_channel', 'metal-rates'),
            'event' => (string) config('metal_rates.broadcast_event', 'rates.updated'),
            'user_channel' => $user ? 'private-user.'.$user->id : null,
            'user_event' => 'assets.updated',
            'next_broadcast_seconds' => (int) config('metal_rates.broadcast_interval_seconds', 30),
            'rates' => $ratesPayload,
            'withdraw_assets' => $withdrawAssets,
            'assets' => $assets,
            'wallet_balance' => $assets['wallet_balance'] ?? null,
            'wallet_balance_display' => $assets['wallet_balance_display'] ?? null,
            'total_assets_balance' => $assets['total_assets_balance'] ?? null,
            'total_assets_balance_display' => $assets['total_assets_balance_display'] ?? null,
            'gold_holdings' => data_get($assets, 'gold.grams'),
            'silver_holdings' => data_get($assets, 'silver.grams'),
            'authenticated' => $user !== null,
            'instruction' => $user
                ? 'Use data.assets + data.withdraw_assets (grams/values). On public rates.updated: update rates only; keep grams. Prefer wallet_amount/total_grams for wallet UI; available_grams may be lower for 48h lock.'
                : 'Send Authorization: Bearer {token} — required to receive available_grams / wallet amounts (otherwise null/rate-only).',
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

    /**
     * @param  array<string, mixed>  $ratesPayload
     * @return array<string, mixed>
     */
    protected function withdrawAssetsForRequest(Request $request, array $ratesPayload): array
    {
        $user = $this->resolveUser($request);

        if ($user instanceof User) {
            return WithdrawAssetsBroadcastPayload::forUser($user->fresh());
        }

        return WithdrawAssetsBroadcastPayload::fromRates($ratesPayload);
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
