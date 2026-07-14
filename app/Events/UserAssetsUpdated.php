<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserAssetsUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $assets
     */
    public function __construct(
        public int $userId,
        public array $assets,
        public string $reason = 'updated',
    ) {}

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
        return [
            'replace' => true,
            'reason' => $this->reason,
            'user_id' => $this->userId,
            'assets' => $this->assets,
            'withdraw_assets' => $this->assets['withdraw_assets'] ?? null,
            'wallet_balance' => $this->assets['wallet_balance'] ?? null,
            'wallet_balance_display' => $this->assets['wallet_balance_display'] ?? null,
            'total_assets_balance' => $this->assets['total_assets_balance'] ?? null,
            'total_assets_balance_display' => $this->assets['total_assets_balance_display'] ?? null,
            'gold_holdings' => $this->assets['gold_holdings'] ?? data_get($this->assets, 'gold.grams'),
            'silver_holdings' => $this->assets['silver_holdings'] ?? data_get($this->assets, 'silver.grams'),
            'gold_value' => data_get($this->assets, 'gold.value'),
            'silver_value' => data_get($this->assets, 'silver.value'),
            'instruction' => 'Replace local gold/silver wallet with assets + withdraw_assets (DB values after purchase). Public metal-rates is rates-only — never null out grams from it.',
        ];
    }
}
