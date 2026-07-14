<?php

namespace App\Services;

use App\Events\UserAssetsUpdated;
use App\Models\Investment;
use App\Models\Payment;
use App\Models\User;
use App\Support\AssetsBalancePayload;
use App\Support\WithdrawAssetsBroadcastPayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MetalPurchaseService
{
    public function __construct(
        protected MetalRateService $metalRates,
        protected GstService $gst,
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
     * Simple buy insert (no Razorpay) — creates completed investment and credits holdings.
     *
     * @param  array{
     *     metal_type: string,
     *     input_mode: string,
     *     amount?: float,
     *     weight_grams?: float,
     *     payment_method?: string,
     *     transaction_id?: string|null
     * }  $data
     * @return array<string, mixed>
     */
    public function purchase(User $user, array $data): array
    {
        $estimate = $this->estimate(
            $user,
            $data['metal_type'],
            $data['input_mode'],
            isset($data['amount']) ? (float) $data['amount'] : null,
            isset($data['weight_grams']) ? (float) $data['weight_grams'] : null,
        );

        $grams = round((float) ($estimate['weight_grams'] ?? 0), 4);
        $rate = round((float) ($estimate['rate_per_gram'] ?? 0), 2);

        if ($rate <= 0) {
            throw ValidationException::withMessages([
                'metal_type' => ['Metal rate is unavailable. Please try again later.'],
            ]);
        }

        if ($grams <= 0) {
            throw ValidationException::withMessages([
                $data['input_mode'] === 'weight' ? 'weight_grams' : 'amount' => [
                    'Purchase quantity must be greater than zero.',
                ],
            ]);
        }

        $result = DB::transaction(function () use ($user, $estimate, $data, $grams): array {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            $investmentData = [
                'user_id' => $lockedUser->id,
                'metal_type' => $estimate['metal_type'],
                'type' => 'buy',
                'quantity_grams' => $grams,
                'rate_per_gram' => $estimate['rate_per_gram'],
                'amount' => $estimate['taxable_amount'],
                'gst_amount' => $estimate['gst_amount'],
                'total_amount' => $estimate['total_amount'],
                'status' => 'completed',
                'notes' => 'Mobile buy metal (direct insert, '.$estimate['input_mode'].')',
            ];

            if (! empty($data['transaction_id'])) {
                $investmentData['reference_id'] = (string) $data['transaction_id'];
            }

            $investment = Investment::query()->create($investmentData);

            $payment = Payment::query()->create([
                'reference_id' => 'PAY-'.strtoupper(uniqid()),
                'user_id' => $lockedUser->id,
                'payable_type' => Investment::class,
                'payable_id' => $investment->id,
                'amount' => $estimate['total_amount'],
                'currency' => 'INR',
                'status' => 'completed',
                'gateway' => $data['payment_method'] ?? 'direct',
                'paid_at' => now(),
            ]);

            // Credit metal holdings immediately (cash wallet_balance is separate).
            if ($estimate['metal_type'] === 'gold') {
                $lockedUser->gold_holdings = round((float) $lockedUser->gold_holdings + $grams, 4);
            } else {
                $lockedUser->silver_holdings = round((float) $lockedUser->silver_holdings + $grams, 4);
            }
            $lockedUser->role = 'investor';
            $lockedUser->save();

            // Reconcile from ledger so buys/sells stay consistent.
            $this->holdings->recalculateForUser($lockedUser->id);

            $fresh = $lockedUser->fresh();
            $assets = AssetsBalancePayload::make($fresh, $this->metalRates);

            return [
                'investment' => $investment->fresh(),
                'payment' => $payment->fresh(),
                'estimate' => $estimate,
                'wallet_balance' => (float) $fresh->wallet_balance,
                'gold_holdings' => (float) $fresh->gold_holdings,
                'silver_holdings' => (float) $fresh->silver_holdings,
                'assets' => $assets,
                'already_completed' => false,
            ];
        });

        // After commit: push updated gold/silver wallet to the user's private channel.
        $withdrawAssets = WithdrawAssetsBroadcastPayload::forUser($user->fresh());
        UserAssetsUpdated::dispatch(
            (int) $user->id,
            array_merge($result['assets'], ['withdraw_assets' => $withdrawAssets]),
            'metal_purchase',
        );

        $result['withdraw_assets'] = $withdrawAssets;

        return $result;
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
            'can_purchase' => true,
            'payment_method' => 'direct',
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
