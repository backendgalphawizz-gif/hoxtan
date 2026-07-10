<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JewelleryEmiPlan extends Model
{
    protected $fillable = [
        'tenure_months',
        'interest_rate_percent',
        'min_order_amount',
        'label',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tenure_months' => 'integer',
            'interest_rate_percent' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(JewelleryOrder::class);
    }

    public function displayLabel(): string
    {
        if (filled($this->label)) {
            return $this->label;
        }

        return $this->tenure_months.' month'.($this->tenure_months === 1 ? '' : 's');
    }

    /**
     * @return array{
     *     tenure_months: int,
     *     interest_rate_percent: float,
     *     total_emi_cost: float,
     *     monthly_emi_amount: float
     * }
     */
    public function calculateForAmount(float $orderTotal): array
    {
        $tenure = max(1, (int) $this->tenure_months);
        $rate = (float) $this->interest_rate_percent;
        $interestAmount = round($orderTotal * ($rate / 100) * ($tenure / 12), 2);
        $totalEmiCost = round($orderTotal + $interestAmount, 2);
        $monthlyEmi = round($totalEmiCost / $tenure, 2);

        return [
            'tenure_months' => $tenure,
            'interest_rate_percent' => $rate,
            'total_emi_cost' => $totalEmiCost,
            'monthly_emi_amount' => $monthlyEmi,
        ];
    }
}
