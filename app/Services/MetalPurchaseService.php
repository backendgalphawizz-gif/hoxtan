<?php

namespace App\Services;

use App\Events\UserAssetsUpdated;
use App\Models\Investment;
use App\Models\Payment;
use App\Models\User;
use App\Support\WalletHoldingsSnapshot;
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
     * When request includes weight_grams, that exact value is stored (not recalculated from amount).
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
        $requestGrams = isset($data['weight_grams']) && (float) $data['weight_grams'] > 0
            ? round((float) $data['weight_grams'], 4)
            : null;

        // If client already sent grams, estimate from weight so we don't convert amount → grams.
        // Amount (if any) is still applied below for payment / GST.
        if ($requestGrams !== null) {
            $estimate = $this->estimateFromWeight($data['metal_type'], $requestGrams);
            $estimate = $this->withUserContext($user, $estimate);
            $estimate['input_mode'] = $data['input_mode'];

            if (isset($data['amount']) && (float) $data['amount'] > 0) {
                $amount = round((float) $data['amount'], 2);
                $gstIncluded = (bool) config('buy_metal.gst_included_for_currency_mode', false);

                if ($gstIncluded) {
                    $totalAmount = $amount;
                    $taxableAmount = round($totalAmount / (1 + $this->gst->rate()), 2);
                    $gstAmount = round($totalAmount - $taxableAmount, 2);
                } else {
                    $taxableAmount = $amount;
                    $gstBreakup = $this->gst->calculateGstAmount($taxableAmount);
                    $gstAmount = $gstBreakup['gst_amount'];
                    $totalAmount = $gstBreakup['total'];
                }

                $estimate['amount'] = $taxableAmount;
                $estimate['amount_display'] = '₹'.number_format($taxableAmount, 2);
                $estimate['taxable_amount'] = $taxableAmount;
                $estimate['gst_amount'] = $gstAmount;
                $estimate['amount_with_gst'] = $totalAmount;
                $estimate['amount_with_gst_display'] = '₹'.number_format($totalAmount, 2);
                $estimate['total_amount'] = $totalAmount;
                $estimate['total_amount_display'] = '₹'.number_format($totalAmount, 2);
            }

            // Force exact client grams (never overwrite from rate math).
            $estimate['weight_grams'] = $requestGrams;
            $estimate['weight_grams_display'] = rtrim(rtrim(number_format($requestGrams, 4, '.', ''), '0'), '.');
            $estimate['estimated_asset_quantity'] = [
                'value' => $requestGrams,
                'unit' => 'grams',
                'label' => $estimate['weight_label'] ?? ('GRAMS OF '.ucfirst($data['metal_type'])),
                'display' => $estimate['weight_grams_display'],
            ];
            $grams = $requestGrams;
        } else {
            $estimate = $this->estimate(
                $user,
                $data['metal_type'],
                $data['input_mode'],
                isset($data['amount']) ? (float) $data['amount'] : null,
                null,
            );
            $grams = round((float) ($estimate['weight_grams'] ?? 0), 4);
        }

        $rate = round((float) ($estimate['rate_per_gram'] ?? 0), 2);

        if ($rate <= 0) {
            throw ValidationException::withMessages([
                'metal_type' => ['Metal rate is unavailable. Please try again later.'],
            ]);
        }

        if ($grams <= 0) {
            throw ValidationException::withMessages([
                ($requestGrams !== null || ($data['input_mode'] ?? '') === 'weight') ? 'weight_grams' : 'amount' => [
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
                'quantity_grams' => $grams, // exact request grams when provided
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

            // Ledger row is source of truth — recalculate gold/silver holdings from investments.
            $lockedUser->role = 'investor';
            $lockedUser->save();
            $this->holdings->recalculateForUser($lockedUser->id);

            $fresh = $lockedUser->fresh();
            $wallet = WalletHoldingsSnapshot::make($fresh, $this->metalRates);

            return [
                'investment' => $investment->fresh(),
                'payment' => $payment->fresh(),
                'estimate' => $estimate,
                'payment_method' => (string) ($data['payment_method'] ?? 'direct'),
                'wallet_balance' => $wallet['wallet_balance'],
                'gold_holdings' => $wallet['gold_holdings'],
                'silver_holdings' => $wallet['silver_holdings'],
                'gold_value' => $wallet['gold_value'],
                'silver_value' => $wallet['silver_value'],
                'assets' => $wallet['assets'],
                'withdraw_assets' => $wallet['withdraw_assets'],
                'already_completed' => false,
            ];
        });

        // After commit: push updated wallet on private WebSocket so app/home refreshes.
        UserAssetsUpdated::dispatchSafe(
            (int) $user->id,
            array_merge($result['assets'], [
                'withdraw_assets' => $result['withdraw_assets'],
                'gold_holdings' => $result['gold_holdings'],
                'silver_holdings' => $result['silver_holdings'],
            ]),
            'metal_purchase',
        );

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function estimateFromCurrency(string $metalType, float $amount): array
    {
        $rate = $this->metalRates->getCurrentRatePerGram($metalType);
        $gstPercent = $this->gst->ratePercent();
        $gstIncluded = (bool) config('buy_metal.gst_included_for_currency_mode', false);

        if ($gstIncluded) {
            // Entered amount = pay total (GST inside) → wallet metal ≈ amount / 1.03
            $totalAmount = round($amount, 2);
            $taxableAmount = round($totalAmount / (1 + $this->gst->rate()), 2);
            $gstAmount = round($totalAmount - $taxableAmount, 2);
        } else {
            // Entered amount = gold/silver wallet value → GST added on top for payment.
            // Buy ₹508 gold → wallet shows ~₹508 (grams × rate); pay ₹508 + GST.
            $taxableAmount = round($amount, 2);
            $gstBreakup = $this->gst->calculateGstAmount($taxableAmount);
            $gstAmount = $gstBreakup['gst_amount'];
            $totalAmount = $gstBreakup['total'];
        }

        $grams = $rate > 0 ? round($taxableAmount / $rate, 4) : 0.0;

        return $this->buildEstimate(
            metalType: $metalType,
            inputMode: 'currency',
            rate: $rate,
            weightGrams: $grams,
            taxableAmount: $taxableAmount,
            gstAmount: $gstAmount,
            totalAmount: $totalAmount,
            gstIncluded: $gstIncluded,
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
            // For currency mode: amount = value credited to metal wallet; amount_with_gst = what customer pays.
            'amount' => $taxableAmount,
            'amount_display' => '₹'.number_format($taxableAmount, 2),
            'taxable_amount' => $taxableAmount,
            'gst_percent' => $gstPercent,
            'gst_amount' => $gstAmount,
            'gst_included' => $gstIncluded,
            'gst_note' => $gstIncluded
                ? 'GST included '.$gstPercent.'%'
                : 'GST '.$gstPercent.'% added on metal value',
            'amount_with_gst' => $totalAmount,
            'amount_with_gst_display' => '₹'.number_format($totalAmount, 2),
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
