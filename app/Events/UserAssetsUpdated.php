<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Compact private wallet update — keep under Pusher payload limits (~10KB).
 */
class UserAssetsUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $assets  full HTTP assets blob (will be slimmed for WS)
     */
    public function __construct(
        public int $userId,
        public array $assets,
        public string $reason = 'updated',
    ) {}

    /**
     * Safe dispatch — never break HTTP APIs if Pusher rejects the payload.
     *
     * @param  array<string, mixed>  $assets
     */
    public static function dispatchSafe(int $userId, array $assets, string $reason = 'updated'): void
    {
        try {
            event(new static($userId, $assets, $reason));
        } catch (BroadcastException|\Throwable $e) {
            Log::warning('UserAssetsUpdated broadcast skipped', [
                'user_id' => $userId,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'assets.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $goldGrams = (float) ($this->assets['gold_holdings'] ?? data_get($this->assets, 'gold.grams', 0));
        $silverGrams = (float) ($this->assets['silver_holdings'] ?? data_get($this->assets, 'silver.grams', 0));
        $sigGrams = (float) ($this->assets['sig_holdings'] ?? data_get($this->assets, 'sig.grams', 0));
        $goldRate = (float) data_get($this->assets, 'gold.rate_per_gram', data_get($this->assets, 'rates.gold', 0));
        $silverRate = (float) data_get($this->assets, 'silver.rate_per_gram', data_get($this->assets, 'rates.silver', 0));
        $goldValue = (float) data_get($this->assets, 'gold.value', round($goldGrams * $goldRate, 2));
        $silverValue = (float) data_get($this->assets, 'silver.value', round($silverGrams * $silverRate, 2));
        $sigValue = (float) ($this->assets['sig_value'] ?? data_get($this->assets, 'sig.value', 0));
        $sigMetal = (string) ($this->assets['sig_metal_type'] ?? data_get($this->assets, 'sig.metal_type', 'gold'));
        $sigRate = (float) data_get($this->assets, 'sig.rate_per_gram', $sigMetal === 'silver' ? $silverRate : $goldRate);

        $withdrawRows = data_get($this->assets, 'withdraw_assets.assets', []);
        $slimWithdraw = [];
        if (is_array($withdrawRows)) {
            foreach ($withdrawRows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $slimWithdraw[] = [
                    'value' => $row['value'] ?? null,
                    'total_grams' => isset($row['total_grams']) ? (float) $row['total_grams'] : null,
                    'available_grams' => isset($row['available_grams']) ? (float) $row['available_grams'] : null,
                    'locked_grams' => isset($row['locked_grams']) ? (float) $row['locked_grams'] : null,
                    'wallet_amount' => isset($row['wallet_amount']) ? (float) $row['wallet_amount'] : null,
                    'available_value' => isset($row['available_value']) ? (float) $row['available_value'] : null,
                    'rate_per_gram' => isset($row['rate_per_gram']) ? round((float) $row['rate_per_gram'], 2) : null,
                ];
            }
        }

        return [
            'replace' => true,
            'reason' => $this->reason,
            'user_id' => $this->userId,
            'gold_holdings' => $goldGrams,
            'silver_holdings' => $silverGrams,
            'sig_holdings' => $sigGrams,
            'sig_metal_type' => $sigMetal,
            'gold_value' => $goldValue,
            'silver_value' => $silverValue,
            'sig_value' => $sigValue,
            'wallet_balance' => (float) ($this->assets['wallet_balance'] ?? 0),
            'total_assets_balance' => (float) ($this->assets['total_assets_balance'] ?? ($goldValue + $silverValue + $sigValue)),
            'assets' => [
                'gold' => [
                    'grams' => $goldGrams,
                    'rate_per_gram' => round($goldRate, 2),
                    'value' => $goldValue,
                    'wallet_amount' => $goldValue,
                ],
                'silver' => [
                    'grams' => $silverGrams,
                    'rate_per_gram' => round($silverRate, 2),
                    'value' => $silverValue,
                    'wallet_amount' => $silverValue,
                ],
                'sig' => [
                    'metal_type' => $sigMetal,
                    'grams' => $sigGrams,
                    'rate_per_gram' => round($sigRate, 2),
                    'value' => $sigValue,
                    'wallet_amount' => $sigValue,
                ],
            ],
            'withdraw_assets' => [
                'assets' => $slimWithdraw,
            ],
        ];
    }
}
