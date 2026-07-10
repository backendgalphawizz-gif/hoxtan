<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MetalPurchaseService
{
    public function __construct(
        protected MetalRateService $metalRates,
        protected GstService $gst,
        protected WalletService $wallet,
        protected UserHoldingsService $holdings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function estimate(User $user, string $metalType, string $inputMode, ?float $amount = null, ?float $weightGrams = null): array
    {
        $estimate = $inputMode === 'weight'
            ? $this->estimateFromWeight($metalType, (float) $weightGrams)
            : $this->estimateFromCurrency($metalType, (float) $amount);

        return $this->withUserContext($user, $estimate);
    }

    /**
     * @param  array{
     *     metal_type: string,
     *     input_mode: string,
     *     amount?: float,
     *     weight_grams?: float,
     *     payment_method?: string
     * }  $data
     * @return array<string, mixed>
     */
    public function purchase(User $user, array $data): array
    {
        if (($data['payment_method'] ?? 'wallet') !== 'wallet') {
            throw ValidationException::withMessages([
                'payment_method' => ['Only wallet payment is supported right now.'],
            ]);
        }

        $estimate = $this->estimate(
            $user,
            $data['metal_type'],
            $data['input_mode'],
            isset($data['amount']) ? (float) $data['amount'] : null,
            isset($data['weight_grams']) ? (float) $data['weight_grams'] : null,
        );

        if (! $estimate['can_purchase']) {
            throw ValidationException::withMessages([
                'wallet_balance' => ['Insufficient wallet balance to complete this purchase.'],
            ]);
        }

        return DB::transaction(function () use ($user, $estimate): array {
            $user->refresh();

            if ((float) $user->wallet_balance < (float) $estimate['total_amount']) {
                throw ValidationException::withMessages([
                    'wallet_balance' => ['Insufficient wallet balance to complete this purchase.'],
                ]);
            }

            $investment = Investment::query()->create([
                'user_id' => $user->id,
                'metal_type' => $estimate['metal_type'],
                'type' => 'buy',
                'quantity_grams' => $estimate['weight_grams'],
                'rate_per_gram' => $estimate['rate_per_gram'],
                'amount' => $estimate['taxable_amount'],
                'gst_amount' => $estimate['gst_amount'],
                'total_amount' => $estimate['total_amount'],
                'status' => 'completed',
                'notes' => 'Mobile buy metal ('.$estimate['input_mode'].')',
            ]);

            $this->wallet->debit(
                $user,
                (float) $estimate['total_amount'],
                'investment',
                'Purchased '.rtrim(rtrim(number_format((float) $estimate['weight_grams'], 4, '.', ''), '0'), '.')
                    .' g '.ucfirst($estimate['metal_type']).' ('.$investment->reference_id.')',
            );

            $this->holdings->recalculateForUser($user->id);
            $user->refresh();

            return [
                'investment' => $investment->fresh(),
                'estimate' => $estimate,
                'wallet_balance' => (float) $user->wallet_balance,
                'gold_holdings' => (float) $user->gold_holdings,
                'silver_holdings' => (float) $user->silver_holdings,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function estimateFromCurrency(string $metalType, float $amount): array
    {
        $rate = $this->metalRates->getCurrentRatePerGram($metalType);
        $gstPercent = $this->gst->ratePercent();
        $taxableAmount = round($amount / (1 + $this->gst->rate()), 2);
        $gstAmount = round($amount - $taxableAmount, 2);
        $grams = $rate > 0 ? round($taxableAmount / $rate, 4) : 0.0;

        return $this->buildEstimate(
            metalType: $metalType,
            inputMode: 'currency',
            rate: $rate,
            weightGrams: $grams,
            taxableAmount: $taxableAmount,
            gstAmount: $gstAmount,
            totalAmount: round($amount, 2),
            gstIncluded: true,
            gstPercent: $gstPercent,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function estimateFromWeight(string $metalType, float $weightGrams): array
    {
        $rate = $this->metalRates->getCurrentRatePerGram($metalType);
        $gstPercent = $this->gst->ratePercent();
        $taxableAmount = round($weightGrams * $rate, 2);
        $gstBreakup = $this->gst->calculateGstAmount($taxableAmount);

        return $this->buildEstimate(
            metalType: $metalType,
            inputMode: 'weight',
            rate: $rate,
            weightGrams: round($weightGrams, 4),
            taxableAmount: $taxableAmount,
            gstAmount: $gstBreakup['gst_amount'],
            totalAmount: $gstBreakup['total'],
            gstIncluded: false,
            gstPercent: $gstPercent,
        );
    }

    /**
     * @param  array<string, mixed>  $estimate
     * @return array<string, mixed>
     */
    protected function withUserContext(User $user, array $estimate): array
    {
        $walletBalance = (float) $user->wallet_balance;

        return array_merge($estimate, [
            'wallet_balance' => $walletBalance,
            'wallet_balance_display' => '₹'.number_format($walletBalance, 2),
            'can_purchase' => $walletBalance >= (float) $estimate['total_amount'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildEstimate(
        string $metalType,
        string $inputMode,
        float $rate,
        float $weightGrams,
        float $taxableAmount,
        float $gstAmount,
        float $totalAmount,
        bool $gstIncluded,
        float $gstPercent,
    ): array {
        $metalConfig = collect(config('buy_metal.metal_types', []))
            ->firstWhere('value', $metalType) ?? [];

        $weightDisplay = rtrim(rtrim(number_format($weightGrams, 4, '.', ''), '0'), '.');

        return [
            'metal_type' => $metalType,
            'metal_type_label' => $metalConfig['label'] ?? ucfirst($metalType),
            'input_mode' => $inputMode,
            'purity' => $metalConfig['purity'] ?? null,
            'purity_display' => $metalConfig['purity_display'] ?? null,
            'rate_per_gram' => round($rate, 2),
            'rate_per_gram_display' => '₹'.number_format($rate, 2).' / gm',
            'amount' => $inputMode === 'currency' ? $totalAmount : $taxableAmount,
            'amount_display' => $inputMode === 'currency'
                ? '₹'.number_format($totalAmount, 2)
                : '₹'.number_format($taxableAmount, 2),
            'taxable_amount' => $taxableAmount,
            'gst_percent' => $gstPercent,
            'gst_amount' => $gstAmount,
            'gst_included' => $gstIncluded,
            'gst_note' => $gstIncluded
                ? 'GST included '.$gstPercent.'%'
                : 'GST '.$gstPercent.'% added on metal value',
            'total_amount' => $totalAmount,
            'total_amount_display' => '₹'.number_format($totalAmount, 2),
            'weight_grams' => $weightGrams,
            'weight_grams_display' => $weightDisplay,
            'weight_label' => $metalConfig['quantity_label'] ?? ('GRAMS OF '.ucfirst($metalType)),
            'estimated_asset_quantity' => [
                'value' => (float) $weightDisplay,
                'unit' => 'grams',
                'label' => $metalConfig['quantity_label'] ?? ('GRAMS OF '.ucfirst($metalType)),
                'display' => $weightDisplay,
            ],
        ];
    }
}
