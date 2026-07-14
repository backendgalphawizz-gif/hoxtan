<?php

namespace App\Http\Controllers\Api;

use App\Events\UserAssetsUpdated;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MetalRateService;
use App\Support\ApiResponse;
use App\Support\AssetsBalancePayload;
use App\Support\MetalRateRealtimeConfig;
use App\Support\WalletHoldingsSnapshot;
use App\Support\WithdrawAssetsBroadcastPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class MetalRateController extends Controller
{
    public function index(Request $request, MetalRateService $rates): JsonResponse
    {
        $this->authenticateFromBearer($request);

        $data = $request->validate([
            'metal_type' => ['nullable', 'string', Rule::in(['gold', 'silver'])],
        ]);

        $payload = $rates->getApiRates(
            filled($data['metal_type'] ?? null) ? $data['metal_type'] : null,
        );

        $wallet = $this->walletForRequest($request, $rates);

        return ApiResponse::success(array_merge($payload, [
            'realtime' => MetalRateRealtimeConfig::make(),
            'assets' => $wallet['assets'],
            'withdraw_assets' => $wallet['withdraw_assets'],
            'gold_holdings' => $wallet['gold_holdings'],
            'silver_holdings' => $wallet['silver_holdings'],
            'authenticated' => $wallet['authenticated'],
        ]));
    }

    public function realtimeConfig(Request $request, MetalRateService $rates): JsonResponse
    {
        $this->authenticateFromBearer($request);
        $ratesPayload = $rates->getApiRates();
        $wallet = $this->walletForRequest($request, $rates);

        return ApiResponse::success([
            'realtime' => MetalRateRealtimeConfig::make(),
            'rates' => $ratesPayload,
            'withdraw_assets' => $wallet['withdraw_assets'],
            'assets' => $wallet['assets'],
            'gold_holdings' => $wallet['gold_holdings'],
            'silver_holdings' => $wallet['silver_holdings'],
            'authenticated' => $wallet['authenticated'],
        ]);
    }

    /**
     * Auth required (sanctum). Returns live rates + this user's gold/silver wallet from DB.
     */
    public function push(Request $request, MetalRateService $rates): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $shouldBroadcast = Cache::add('metal_rates:push_lock', 1, now()->addSeconds(2));

        if ($shouldBroadcast) {
            try {
                $rates->broadcastCurrentRates();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('rates/push public broadcast skipped', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $ratesPayload = $rates->getApiRates();
        $snapshot = WalletHoldingsSnapshot::make($user->fresh(), $rates);

        UserAssetsUpdated::dispatchSafe(
            (int) $user->id,
            array_merge($snapshot['assets'], [
                'withdraw_assets' => $snapshot['withdraw_assets'],
                'gold_holdings' => $snapshot['gold_holdings'],
                'silver_holdings' => $snapshot['silver_holdings'],
            ]),
            'rates_push',
        );

        return ApiResponse::success([
            'pushed' => $shouldBroadcast,
            'channel' => (string) config('metal_rates.broadcast_channel', 'metal-rates'),
            'event' => (string) config('metal_rates.broadcast_event', 'rates.updated'),
            'user_channel' => 'private-user.'.$user->id,
            'user_event' => 'assets.updated',
            'next_broadcast_seconds' => (int) config('metal_rates.broadcast_interval_seconds', 30),
            'rates' => $ratesPayload,
            'withdraw_assets' => $snapshot['withdraw_assets'],
            'assets' => $snapshot['assets'],
            'wallet_balance' => $snapshot['wallet_balance'],
            'wallet_balance_display' => $snapshot['wallet_balance_display'],
            'total_assets_balance' => $snapshot['total_assets_balance'],
            'total_assets_balance_display' => $snapshot['total_assets_balance_display'],
            'gold_holdings' => $snapshot['gold_holdings'],
            'silver_holdings' => $snapshot['silver_holdings'],
            'gold_value' => $snapshot['gold_value'],
            'silver_value' => $snapshot['silver_value'],
            'gold_value_display' => $snapshot['gold_value_display'],
            'silver_value_display' => $snapshot['silver_value_display'],
            'authenticated' => true,
            'user_id' => $user->id,
            'instruction' => 'Wallet values come from your DB holdings after buy-metal/purchase. Use gold_holdings/silver_holdings and withdraw_assets.assets[].total_grams / available_grams / wallet_amount.',
        ], $shouldBroadcast
            ? 'Rates pushed to WebSocket subscribers.'
            : 'Rates returned; WebSocket push debounced (another push ran recently).');
    }

    /**
     * @return array<string, mixed>
     */
    protected function walletForRequest(Request $request, MetalRateService $rates): array
    {
        $user = $this->resolveUser($request);
        $ratesPayload = $rates->getApiRates();

        if (! $user instanceof User) {
            return [
                'authenticated' => false,
                'gold_holdings' => null,
                'silver_holdings' => null,
                'gold_value' => null,
                'silver_value' => null,
                'gold_value_display' => null,
                'silver_value_display' => null,
                'wallet_balance' => null,
                'wallet_balance_display' => null,
                'total_assets_balance' => null,
                'total_assets_balance_display' => null,
                'assets' => AssetsBalancePayload::broadcastShellFromRates($ratesPayload),
                'withdraw_assets' => WithdrawAssetsBroadcastPayload::fromRates($ratesPayload),
            ];
        }

        return array_merge(WalletHoldingsSnapshot::make($user, $rates), ['authenticated' => true]);
    }

    protected function authenticateFromBearer(Request $request): void
    {
        if ($request->user() instanceof User) {
            return;
        }

        $user = $this->resolveUser($request);
        if ($user instanceof User) {
            Auth::guard('sanctum')->setUser($user);
            $request->setUserResolver(static fn () => $user);
        }
    }

    protected function resolveUser(Request $request): ?User
    {
        $user = $request->user();
        if ($user instanceof User) {
            return $user;
        }

        $plain = $request->bearerToken()
            ?? $request->header('X-Access-Token')
            ?? $request->input('token')
            ?? $request->query('token');

        if (! filled($plain)) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken((string) $plain);
        $tokenable = $accessToken?->tokenable;

        return $tokenable instanceof User ? $tokenable : null;
    }
}
