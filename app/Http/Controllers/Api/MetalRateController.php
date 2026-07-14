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
     * Instant rates + (with Bearer token) full gold/silver wallet after purchases.
     */
    public function push(Request $request, MetalRateService $rates): JsonResponse
    {
        $this->authenticateFromBearer($request);

        $shouldBroadcast = Cache::add('metal_rates:push_lock', 1, now()->addSeconds(2));

        if ($shouldBroadcast) {
            $rates->broadcastCurrentRates();
        }

        $ratesPayload = $rates->getApiRates();
        $wallet = $this->walletForRequest($request, $rates);
        $user = $this->resolveUser($request);

        if ($user instanceof User) {
            UserAssetsUpdated::dispatch(
                (int) $user->id,
                array_merge($wallet['assets'], [
                    'withdraw_assets' => $wallet['withdraw_assets'],
                    'gold_holdings' => $wallet['gold_holdings'],
                    'silver_holdings' => $wallet['silver_holdings'],
                ]),
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
            'withdraw_assets' => $wallet['withdraw_assets'],
            'assets' => $wallet['assets'],
            'wallet_balance' => $wallet['wallet_balance'],
            'wallet_balance_display' => $wallet['wallet_balance_display'],
            'total_assets_balance' => $wallet['total_assets_balance'],
            'total_assets_balance_display' => $wallet['total_assets_balance_display'],
            'gold_holdings' => $wallet['gold_holdings'],
            'silver_holdings' => $wallet['silver_holdings'],
            'gold_value' => $wallet['gold_value'],
            'silver_value' => $wallet['silver_value'],
            'gold_value_display' => $wallet['gold_value_display'],
            'silver_value_display' => $wallet['silver_value_display'],
            'authenticated' => $wallet['authenticated'],
            'instruction' => $wallet['authenticated']
                ? 'Wallet updated from DB after buy-metal/purchase. Use gold_holdings/silver_holdings and withdraw_assets.total_grams / wallet_amount for UI. available_grams may be lower for 48h lock. Public rates.updated is rates-only.'
                : 'Send Authorization: Bearer {token} to receive gold/silver wallet after purchase.',
        ], $shouldBroadcast
            ? 'Rates pushed to WebSocket subscribers.'
            : 'Rates returned; WebSocket push debounced (another push ran recently).');
    }

    /**
     * @return array{
     *     authenticated: bool,
     *     gold_holdings: float|null,
     *     silver_holdings: float|null,
     *     gold_value: float|null,
     *     silver_value: float|null,
     *     gold_value_display: string|null,
     *     silver_value_display: string|null,
     *     wallet_balance: float|null,
     *     wallet_balance_display: string|null,
     *     total_assets_balance: float|null,
     *     total_assets_balance_display: string|null,
     *     assets: array<string, mixed>,
     *     withdraw_assets: array<string, mixed>
     * }
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

        $snapshot = WalletHoldingsSnapshot::make($user, $rates);

        return array_merge($snapshot, ['authenticated' => true]);
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

        $plain = $request->bearerToken();
        if (! filled($plain)) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($plain);
        $tokenable = $accessToken?->tokenable;

        return $tokenable instanceof User ? $tokenable : null;
    }
}
