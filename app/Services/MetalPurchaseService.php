<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\Payment;
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
        protected RazorpayService $razorpay,
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
     * Create a pending buy + Razorpay order for the mobile SDK.
     *
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
        $method = $data['payment_method'] ?? 'razorpay';

        if ($method !== 'razorpay') {
            throw ValidationException::withMessages([
                'payment_method' => ['Only Razorpay payment is supported.'],
            ]);
        }

        $estimate = $this->estimate(
            $user,
            $data['metal_type'],
            $data['input_mode'],
            isset($data['amount']) ? (float) $data['amount'] : null,
            isset($data['weight_grams']) ? (float) $data['weight_grams'] : null,
        );

        return DB::transaction(function () use ($user, $estimate): array {
            $investment = Investment::query()->create([
                'user_id' => $user->id,
                'metal_type' => $estimate['metal_type'],
                'type' => 'buy',
                'quantity_grams' => $estimate['weight_grams'],
                'rate_per_gram' => $estimate['rate_per_gram'],
                'amount' => $estimate['taxable_amount'],
                'gst_amount' => $estimate['gst_amount'],
                'total_amount' => $estimate['total_amount'],
                'status' => 'pending',
                'notes' => 'Mobile buy metal via Razorpay ('.$estimate['input_mode'].')',
            ]);

            $payment = Payment::query()->create([
                'reference_id' => 'PAY-'.strtoupper(uniqid()),
                'user_id' => $user->id,
                'payable_type' => Investment::class,
                'payable_id' => $investment->id,
                'amount' => $estimate['total_amount'],
                'currency' => 'INR',
                'status' => 'pending',
                'gateway' => 'razorpay',
            ]);

            $order = $this->razorpay->createOrder(
                (float) $estimate['total_amount'],
                $payment->reference_id,
                [
                    'investment_id' => (string) $investment->id,
                    'payment_id' => (string) $payment->id,
                    'user_id' => (string) $user->id,
                    'metal_type' => (string) $estimate['metal_type'],
                ],
            );

            $payment->update([
                'gateway_reference' => $order['id'],
                'status' => 'processing',
            ]);

            $user->refresh();

            return [
                'investment' => $investment->fresh(),
                'payment' => $payment->fresh(),
                'estimate' => $estimate,
                'razorpay' => [
                    'key' => $this->razorpay->keyId(),
                    'order_id' => $order['id'],
                    'amount' => $order['amount'],
                    'currency' => $order['currency'],
                    'name' => config('app.name', 'Hoxtan'),
                    'description' => 'Buy '.ucfirst($estimate['metal_type']).' — '.$investment->reference_id,
                    'prefill' => [
                        'name' => $user->name,
                        'email' => $user->email,
                        'contact' => $user->phone,
                    ],
                    'notes' => [
                        'investment_id' => (string) $investment->id,
                        'payment_reference' => $payment->reference_id,
                    ],
                ],
                'wallet_balance' => (float) $user->wallet_balance,
                'gold_holdings' => (float) $user->gold_holdings,
                'silver_holdings' => (float) $user->silver_holdings,
            ];
        });
    }

    /**
     * Verify Razorpay payment and credit metal holdings.
     *
     * @param  array{
     *     razorpay_order_id: string,
     *     razorpay_payment_id: string,
     *     razorpay_signature: string
     * }  $data
     * @return array<string, mixed>
     */
    public function verify(User $user, array $data): array
    {
        $this->razorpay->verifyPaymentSignature(
            $data['razorpay_order_id'],
            $data['razorpay_payment_id'],
            $data['razorpay_signature'],
        );

        $remote = $this->razorpay->fetchPayment($data['razorpay_payment_id']);

        if (! in_array($remote['status'], ['captured', 'authorized'], true)) {
            throw ValidationException::withMessages([
                'razorpay_payment_id' => ['Payment is not successful yet (status: '.$remote['status'].').'],
            ]);
        }

        if ($remote['order_id'] !== $data['razorpay_order_id']) {
            throw ValidationException::withMessages([
                'razorpay_order_id' => ['Payment does not match the Razorpay order.'],
            ]);
        }

        return DB::transaction(function () use ($user, $data, $remote): array {
            $payment = Payment::query()
                ->where('user_id', $user->id)
                ->where('gateway', 'razorpay')
                ->where('gateway_reference', $data['razorpay_order_id'])
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw ValidationException::withMessages([
                    'razorpay_order_id' => ['Payment order not found.'],
                ]);
            }

            /** @var Investment|null $investment */
            $investment = $payment->payable_type === Investment::class
                ? Investment::query()->lockForUpdate()->find($payment->payable_id)
                : null;

            if (! $investment || $investment->user_id !== $user->id || $investment->type !== 'buy') {
                throw ValidationException::withMessages([
                    'payment' => ['Related metal purchase not found.'],
                ]);
            }

            $expectedPaise = (int) round((float) $payment->amount * 100);
            if ((int) $remote['amount'] !== $expectedPaise) {
                throw ValidationException::withMessages([
                    'amount' => ['Paid amount does not match the purchase amount.'],
                ]);
            }

            if ($payment->status === 'completed' && $investment->status === 'completed') {
                $user->refresh();

                return [
                    'investment' => $investment->fresh(),
                    'payment' => $payment->fresh(),
                    'estimate' => null,
                    'wallet_balance' => (float) $user->wallet_balance,
                    'gold_holdings' => (float) $user->gold_holdings,
                    'silver_holdings' => (float) $user->silver_holdings,
                    'already_completed' => true,
                ];
            }

            $payment->update([
                'status' => 'completed',
                'paid_at' => now(),
                'gateway_payment_id' => $data['razorpay_payment_id'],
                'failure_reason' => null,
            ]);

            if ($investment->status !== 'completed') {
                $investment->update([
                    'status' => 'completed',
                    'notes' => trim(($investment->notes ?? '').' | paid='.$data['razorpay_payment_id']),
                ]);
            }

            $this->holdings->recalculateForUser($user->id);
            $user->refresh();

            return [
                'investment' => $investment->fresh(),
                'payment' => $payment->fresh(),
                'estimate' => null,
                'wallet_balance' => (float) $user->wallet_balance,
                'gold_holdings' => (float) $user->gold_holdings,
                'silver_holdings' => (float) $user->silver_holdings,
                'already_completed' => false,
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
            'can_purchase' => true,
            'payment_method' => 'razorpay',
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
