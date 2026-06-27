<?php

namespace App\Services;

use App\Models\MetalRate;
use Illuminate\Support\Facades\Auth;

class MetalRateService
{
    public function getLiveRate(string $metalType): float
    {
        return $metalType === 'gold' ? 7250.00 : 85.50;
    }

    public function getActiveRate(string $metalType): ?MetalRate
    {
        return MetalRate::query()
            ->where('metal_type', $metalType)
            ->where('is_active', true)
            ->latest()
            ->first();
    }

    public function syncLiveRate(string $metalType): MetalRate
    {
        $rate = $this->getLiveRate($metalType);

        return MetalRate::create([
            'metal_type' => $metalType,
            'rate_per_gram' => $rate,
            'source' => 'live_sync',
            'is_active' => true,
            'updated_by' => Auth::guard('admin')->id(),
            'notes' => 'Synced from live market feed',
        ]);
    }
}
