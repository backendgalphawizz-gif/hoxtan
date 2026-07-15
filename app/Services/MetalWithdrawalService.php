<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\MetalWithdrawal;
use App\Models\SigInstallment;
use App\Models\SigPlan;
use App\Models\User;
use App\Support\AssetsBalancePayload;
use App\Support\NavigationBadgeCounts;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MetalWithdrawalService
{
    public function __construct(
        protected MetalRateService $metalRates,
        protected UserHoldingsService $holdings,
        protected NotificationInboxService $notifications,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function assets(User $user): array
    {
        $this->assertWithdrawalAllowed($user);

        $balances = AssetsBalancePayload::make($user, $this->metalRates);
        $bank = $this->bankSnapshot($user);

        $assets = [];
        foreach (config('withdraw.assets', []) as $asset) {
            $key = $asset['value'];
            $row = $balances[$key] ?? null;
            if (! is_array($row)) {
                continue;
            }

            $availableGrams = $this->availableGrams($user, $key);
            $breakdown = $this->availabilityBreakdown($user, $key);
            $rate = (float) ($row['rate_per_gram'] ?? 0);
            $availableValue = round($availableGrams * $rate, 2);
            $totalGrams = (float) ($breakdown['total_grams'] ?? 0);
            $walletAmount = round($totalGrams * $rate, 2);

            $assets[] = [
                'value' => $key,
                'label' => $asset['label'] ?? ucfirst($key),
                'screen_title' => $asset['screen_title'] ?? ('Withdraw '.($asset['label'] ?? ucfirst($key))),
                'metal_type' => $key === 'sig' ? ($row['metal_type'] ?? 'gold') : $key,
                // Wallet holdings (always reflects purchases immediately).
                'total_grams' => $totalGrams,
                'total_grams_display' => number_format($totalGrams, 2).'g',
                'holdings_grams' => $totalGrams,
                'holdings_grams_display' => number_format($totalGrams, 2).'g',
                'balance_grams' => $totalGrams,
                'wallet_amount' => $walletAmount,
                'wallet_amount_display' => '₹'.number_format($walletAmount, 2),
                // Withdrawal eligibility (may be lower for 48h lock).
                'locked_grams' => $breakdown['locked_grams'],
                'locked_grams_display' => number_format($breakdown['locked_grams'], 2).'g',
                'available_grams' => $availableGrams,
                'available_grams_display' => number_format($availableGrams, 2).'g',
                'available_value' => $availableValue,
                'available_value_display' => '₹'.number_format($availableValue, 2),
                'rate_per_gram' => $rate,
                'rate_per_gram_display' => '₹'.number_format($rate, 2).' / gm',
                'can_withdraw' => $availableValue >= (float) config('withdraw.min_amount', 1000),
                'holding_period_hours' => (int) config('withdraw.holding_period_hours', 48),
            ];
        }

        return [
            'title' => config('withdraw.select_title', 'Select Assets to Withdraw'),
            'min_amount' => (float) config('withdraw.min_amount', 1000),
            'min_amount_note' => config('withdraw.min_amount_note'),
            'note' => config('withdraw.note'),
            'holding_period_hours' => (int) config('withdraw.holding_period_hours', 48),
            'holding_period_message' => config('withdraw.holding_period_message'),
            'hold_bonus_percent' => (float) config('withdraw.hold_bonus_percent', 1),
            'hold_bonus_message' => config('withdraw.hold_bonus_message'),
            'input_modes' => config('withdraw.input_modes', []),
            'preset_amounts' => config('withdraw.preset_amounts', []),
            'auto_approve_hours' => (int) config('withdraw.auto_approve_hours', 2),
            // Top-level wallet (updated on every gold/silver/SIG credit).
            'gold_holdings' => (float) data_get($balances, 'gold.grams', 0),
            'silver_holdings' => (float) data_get($balances, 'silver.grams', 0),
            'sig_holdings' => (float) data_get($balances, 'sig.grams', 0),
            'sig_metal_type' => (string) data_get($balances, 'sig.metal_type', 'gold'),
            'gold_value' => (float) data_get($balances, 'gold.value', 0),
            'silver_value' => (float) data_get($balances, 'silver.value', 0),
            'sig_value' => (float) data_get($balances, 'sig.value', 0),
            'total_assets_balance' => (float) data_get($balances, 'total_assets_balance', 0),
            'total_assets_balance_display' => (string) data_get($balances, 'total_assets_balance_display', '₹0.00'),
            'assets' => $assets,
            'balances' => $balances,
            'bank' => $bank,
            'has_bank' => $bank !== null,
            'websocket' => [
                'replace' => true,
                'event' => (string) config('metal_rates.broadcast_event', 'rates.updated'),
                'channel' => (string) config('metal_rates.broadcast_channel', 'metal-rates'),
                'field' => 'withdraw_assets',
                'instruction' => 'On public rates.updated: update rate_per_gram only; keep available_grams/total_grams/locked_grams/bank from this HTTP response (or from authenticated rates/push). available_value = available_grams × rate; wallet_amount = total_grams × rate. Do not overwrite grams with null.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function screen(User $user, string $assetSource): array
    {
        $this->assertWithdrawalAllowed($user);
        $assetSource = $this->normalizeAssetSource($assetSource);

        $assetsPayload = $this->assets($user);
        $asset = collect($assetsPayload['assets'])->firstWhere('value', $assetSource);

        if (! $asset) {
            throw ValidationException::withMessages([
                'asset_source' => ['Invalid asset selected.'],
            ]);
        }

        return [
            'title' => $asset['screen_title'],
            'asset_source' => $assetSource,
            'asset' => $asset,
            'input_modes' => config('withdraw.input_modes', []),
            'preset_amounts' => config('withdraw.preset_amounts', []),
            'min_amount' => (float) config('withdraw.min_amount', 1000),
            'min_amount_note' => config('withdraw.min_amount_note'),
            'note' => config('withdraw.note'),
            'holding_period_hours' => (int) config('withdraw.holding_period_hours', 48),
            'holding_period_message' => config('withdraw.holding_period_message'),
            'hold_bonus_percent' => (float) config('withdraw.hold_bonus_percent', 1),
            'hold_bonus_message' => config('withdraw.hold_bonus_message'),
            'auto_approve_hours' => (int) config('withdraw.auto_approve_hours', 2),
            'bank' => $assetsPayload['bank'],
            'has_bank' => $assetsPayload['has_bank'],
            'withdraw_to_label' => 'Withdraw to',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function estimate(
        User $user,
        string $assetSource,
        string $inputMode,
        ?float $amount = null,
        ?float $weightGrams = null,
    ): array {
        $this->assertWithdrawalAllowed($user);
        $assetSource = $this->normalizeAssetSource($assetSource);
        $estimate = $this->buildEstimate($user, $assetSource, $inputMode, $amount, $weightGrams);

        return $estimate;
    }

    /**
     * @param  array{
     *     asset_source: string,
     *     input_mode: string,
     *     amount?: float,
     *     weight_grams?: float
     * }  $data
     * @return array{withdrawal: MetalWithdrawal, estimate: array<string, mixed>}
     */
    public function create(User $user, array $data): array
    {
        $this->assertWithdrawalAllowed($user);

        $bank = $this->bankSnapshot($user);
        if ($bank === null) {
            throw ValidationException::withMessages([
                'bank' => ['Please submit your bank details in KYC before withdrawing.'],
            ]);
        }

        $assetSource = $this->normalizeAssetSource($data['asset_source']);
        $fromHoldings = (bool) ($data['from_holdings'] ?? false);
        $estimate = $this->buildEstimate(
            $user,
            $assetSource,
            $data['input_mode'],
            isset($data['amount']) ? (float) $data['amount'] : null,
            isset($data['weight_grams']) ? (float) $data['weight_grams'] : null,
            $fromHoldings,
            isset($data['holdings_sell_after_hours']) ? (int) $data['holdings_sell_after_hours'] : null,
        );

        if (! $estimate['can_withdraw']) {
            throw ValidationException::withMessages([
                $data['input_mode'] === 'weight' ? 'weight_grams' : 'amount' => [
                    $estimate['block_reason'] ?? 'Insufficient balance for this withdrawal.',
                ],
            ]);
        }

        $hours = $fromHoldings
            ? max(0, (int) ($data['holdings_auto_approve_hours'] ?? config('holdings.sell_auto_approve_hours', 2)))
            : max(0, (int) config('withdraw.auto_approve_hours', 2));
        $sourceLotId = isset($data['source_lot_id']) ? (int) $data['source_lot_id'] : null;

        if ($fromHoldings && $sourceLotId) {
            $alreadyPending = MetalWithdrawal::query()
                ->where('user_id', $user->id)
                ->where('source_lot_id', $sourceLotId)
                ->where('from_holdings', true)
                ->where('status', 'pending')
                ->exists();

            if ($alreadyPending) {
                throw ValidationException::withMessages([
                    'lot_id' => [
                        'A sell request for this lot is already pending. You cannot sell this lot again until that request is completed or rejected.',
                    ],
                ]);
            }
        }

        $notes = null;
        if ($fromHoldings) {
            $parts = ['Holdings sell'];
            if (! empty($data['payment_method'])) {
                $parts[] = (string) $data['payment_method'];
            }
            if (! empty($data['transaction_id'])) {
                $parts[] = (string) $data['transaction_id'];
            }
            $notes = implode(' | ', $parts);
        }

        $withdrawal = DB::transaction(function () use ($user, $assetSource, $estimate, $bank, $hours, $data, $sourceLotId, $notes, $fromHoldings): MetalWithdrawal {
            $sigPlanId = null;
            if ($assetSource === 'sig') {
                $sigPlanId = $this->activeSigPlan($user)?->id;
            }

            return MetalWithdrawal::query()->create([
                'user_id' => $user->id,
                'asset_source' => $assetSource,
                'metal_type' => $estimate['metal_type'],
                'input_mode' => $data['input_mode'],
                'quantity_grams' => $estimate['weight_grams'],
                'rate_per_gram' => $estimate['rate_per_gram'],
                'amount' => $estimate['amount'],
                'status' => 'pending',
                'bank_name' => $bank['bank_name'],
                'account_holder_name' => $bank['account_holder_name'],
                'account_number' => $bank['account_number'],
                'ifsc_code' => $bank['ifsc_code'],
                'sig_plan_id' => $sigPlanId,
                'source_lot_id' => $sourceLotId,
                'from_holdings' => $fromHoldings,
                'admin_notes' => $notes,
                'requested_at' => now(),
                'auto_approve_at' => $hours > 0 ? now()->addHours($hours) : null,
            ]);
        });

        NavigationBadgeCounts::clearCache();

        $this->notifyAdminsOfNewRequest($user, $withdrawal);

        return [
            'withdrawal' => $withdrawal->fresh(),
            'estimate' => $estimate,
        ];
    }

    private function notifyAdminsOfNewRequest(User $user, MetalWithdrawal $withdrawal): void
    {
        $asset = strtoupper((string) $withdrawal->asset_source);
        $amount = '₹'.number_format((float) $withdrawal->amount, 2);
        $grams = number_format((float) $withdrawal->quantity_grams, 4).'g';
        $reference = (string) ($withdrawal->reference_id ?? '#'.$withdrawal->id);

        $this->notifications->notifyAdmins(
            'New withdrawal request',
            "{$user->name} requested {$asset} withdrawal of {$amount} ({$grams}). Ref: {$reference}.",
            'metal_withdrawal',
            [
                'metal_withdrawal_id' => (string) $withdrawal->id,
                'reference_id' => $reference,
                'user_id' => (string) $user->id,
                'user_name' => (string) $user->name,
                'asset_source' => (string) $withdrawal->asset_source,
                'metal_type' => (string) $withdrawal->metal_type,
                'amount' => (string) $withdrawal->amount,
                'quantity_grams' => (string) $withdrawal->quantity_grams,
                'status' => (string) $withdrawal->status,
            ],
        );
    }

    public function approve(MetalWithdrawal $withdrawal, ?int $adminId = null, ?string $payoutReference = null, bool $auto = false): MetalWithdrawal
    {
        if (! $withdrawal->isPending()) {
            throw ValidationException::withMessages([
                'withdrawal' => ['Only pending withdrawals can be approved.'],
            ]);
        }

        return DB::transaction(function () use ($withdrawal, $adminId, $payoutReference, $auto): MetalWithdrawal {
            $withdrawal = MetalWithdrawal::query()->lockForUpdate()->findOrFail($withdrawal->id);

            if (! $withdrawal->isPending()) {
                throw ValidationException::withMessages([
                    'withdrawal' => ['Only pending withdrawals can be approved.'],
                ]);
            }

            $user = User::query()->lockForUpdate()->findOrFail($withdrawal->user_id);
            $grams = (float) $withdrawal->quantity_grams;

            // Payout at current live rate when admin/auto approves.
            $currentRate = round((float) $this->metalRates->getCurrentRatePerGram((string) $withdrawal->metal_type), 2);
            if ($currentRate <= 0) {
                $currentRate = round((float) $withdrawal->rate_per_gram, 2);
            }
            $payoutAmount = round($grams * $currentRate, 2);

            $available = $this->availableGrams($user, $withdrawal->asset_source, excludeWithdrawalId: $withdrawal->id);
            $fromHoldings = str_contains((string) $withdrawal->admin_notes, 'Holdings sell');
            if ($fromHoldings) {
                $sellAfter = (int) config('holdings.sell_after_hours', 48);
                $available = $this->holdings->sellableGrams(
                    (int) $user->id,
                    (string) $withdrawal->metal_type,
                    $sellAfter,
                );
            }

            if ($grams > $available + 0.00005) {
                throw ValidationException::withMessages([
                    'withdrawal' => ['User no longer has enough balance to approve this withdrawal.'],
                ]);
            }

            $investment = null;

            if ($withdrawal->asset_source === 'sig') {
                $plan = SigPlan::query()->lockForUpdate()->find($withdrawal->sig_plan_id);
                if (! $plan || (float) $plan->metal_accumulated_grams + 0.00005 < $grams) {
                    throw ValidationException::withMessages([
                        'withdrawal' => ['SIG balance is insufficient to approve this withdrawal.'],
                    ]);
                }

                $plan->update([
                    'metal_accumulated_grams' => max(0, round((float) $plan->metal_accumulated_grams - $grams, 4)),
                ]);
            } else {
                $investment = Investment::query()->create([
                    'user_id' => $user->id,
                    'metal_type' => $withdrawal->metal_type,
                    'type' => 'sell',
                    'quantity_grams' => $withdrawal->quantity_grams,
                    'rate_per_gram' => $currentRate,
                    'amount' => $payoutAmount,
                    'gst_amount' => 0,
                    'total_amount' => $payoutAmount,
                    'status' => 'completed',
                    'notes' => 'Metal withdrawal '.$withdrawal->reference_id.($auto ? ' (auto-approved)' : ''),
                ]);

                $this->holdings->consumeHoldLots(
                    (int) $user->id,
                    (string) $withdrawal->metal_type,
                    (float) $withdrawal->quantity_grams,
                    $withdrawal->source_lot_id ? (int) $withdrawal->source_lot_id : null,
                    $fromHoldings ? (int) config('holdings.sell_after_hours', 48) : null,
                );
                $this->holdings->recalculateForUser($user->id);
            }

            $withdrawal->update([
                'rate_per_gram' => $currentRate,
                'amount' => $payoutAmount,
                'status' => $auto ? 'approved' : 'paid',
                'auto_approved' => $auto,
                'investment_id' => $investment?->id,
                'payout_reference' => $payoutReference,
                'reviewed_by' => $auto ? null : $adminId,
                'reviewed_at' => now(),
                'paid_at' => now(),
            ]);

            NavigationBadgeCounts::clearCache();

            return $withdrawal->fresh(['user', 'reviewer', 'investment', 'sigPlan']);
        });
    }

    public function reject(MetalWithdrawal $withdrawal, int $adminId, string $reason): MetalWithdrawal
    {
        if (! $withdrawal->isPending()) {
            throw ValidationException::withMessages([
                'withdrawal' => ['Only pending withdrawals can be rejected.'],
            ]);
        }

        $withdrawal->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
        ]);

        NavigationBadgeCounts::clearCache();

        return $withdrawal->fresh(['user', 'reviewer']);
    }

    public function autoApproveExpired(): int
    {
        $count = 0;

        MetalWithdrawal::query()
            ->where('status', 'pending')
            ->whereNotNull('auto_approve_at')
            ->where('auto_approve_at', '<=', now())
            ->orderBy('id')
            ->each(function (MetalWithdrawal $withdrawal) use (&$count): void {
                try {
                    $this->approve($withdrawal, auto: true);
                    $count++;
                } catch (\Throwable) {
                    // Skip if balance no longer available; remains pending for manual review.
                }
            });

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    public function withdrawalPayload(MetalWithdrawal $withdrawal): array
    {
        return [
            'id' => $withdrawal->id,
            'reference_id' => $withdrawal->reference_id,
            'asset_source' => $withdrawal->asset_source,
            'metal_type' => $withdrawal->metal_type,
            'input_mode' => $withdrawal->input_mode,
            'quantity_grams' => (float) $withdrawal->quantity_grams,
            'quantity_grams_display' => number_format((float) $withdrawal->quantity_grams, 2).'g',
            'rate_per_gram' => (float) $withdrawal->rate_per_gram,
            'amount' => (float) $withdrawal->amount,
            'amount_display' => '₹'.number_format((float) $withdrawal->amount, 2),
            'status' => $withdrawal->status,
            'bank' => [
                'bank_name' => $withdrawal->bank_name,
                'account_holder_name' => $withdrawal->account_holder_name,
                'account_number_masked' => $withdrawal->maskedAccountNumber(),
                'ifsc_code' => $withdrawal->ifsc_code,
            ],
            'requested_at' => $withdrawal->requested_at?->toIso8601String(),
            'auto_approve_at' => $withdrawal->auto_approve_at?->toIso8601String(),
            'auto_approved' => (bool) $withdrawal->auto_approved,
            'reviewed_at' => $withdrawal->reviewed_at?->toIso8601String(),
            'paid_at' => $withdrawal->paid_at?->toIso8601String(),
            'payout_reference' => $withdrawal->payout_reference,
            'rejection_reason' => $withdrawal->rejection_reason,
            'from_holdings' => (bool) $withdrawal->from_holdings,
            'source_lot_id' => $withdrawal->source_lot_id,
            'investment_id' => $withdrawal->investment_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildEstimate(
        User $user,
        string $assetSource,
        string $inputMode,
        ?float $amount,
        ?float $weightGrams,
        bool $fromHoldings = false,
        ?int $holdingsSellAfterHours = null,
    ): array {
        $metalType = $this->metalTypeForAsset($user, $assetSource);
        $rate = (float) $this->metalRates->getCurrentRatePerGram($metalType);

        if ($fromHoldings) {
            $sellAfter = $holdingsSellAfterHours ?? (int) config('holdings.sell_after_hours', 48);
            $availableGrams = $this->holdings->sellableGrams($user->id, $metalType, $sellAfter);
            $total = round((float) ($metalType === 'silver' ? $user->silver_holdings : $user->gold_holdings), 4);
            $locked = max(0, round($total - $availableGrams, 4));
            $breakdown = [
                'locked_grams' => $locked,
                'available_grams' => $availableGrams,
            ];
            $minAmount = 0.0;
            $holdingPeriodHours = $sellAfter;
            $holdingPeriodMessage = config('holdings.sell_after_message');
        } else {
            $availableGrams = $this->availableGrams($user, $assetSource);
            $breakdown = $this->availabilityBreakdown($user, $assetSource);
            $minAmount = (float) config('withdraw.min_amount', 1000);
            $holdingPeriodHours = (int) config('withdraw.holding_period_hours', 48);
            $holdingPeriodMessage = config('withdraw.holding_period_message');
        }

        $availableValue = round($availableGrams * $rate, 2);

        if ($inputMode === 'weight') {
            $grams = round((float) $weightGrams, 4);
            $receiveAmount = round($grams * $rate, 2);
        } else {
            $receiveAmount = round((float) $amount, 2);
            $grams = $rate > 0 ? round($receiveAmount / $rate, 4) : 0.0;
        }

        $blockReason = null;
        if (! $fromHoldings && $receiveAmount < $minAmount) {
            $blockReason = 'Minimum withdrawal amount is ₹'.number_format($minAmount, 0).'.';
        } elseif ($grams > $availableGrams + 0.00005) {
            $locked = $breakdown['locked_grams'];
            $blockReason = $locked > 0
                ? 'Only metal held for '.$holdingPeriodHours.' hours can be sold/withdrawn. Locked: '.number_format($locked, 2).'g.'
                : 'Insufficient '.strtoupper($assetSource).' balance for this request.';
        }

        $assetConfig = collect(config('withdraw.assets', []))->firstWhere('value', $assetSource) ?? [];
        $weightDisplay = rtrim(rtrim(number_format($grams, 4, '.', ''), '0'), '.');

        return [
            'asset_source' => $assetSource,
            'asset_label' => $assetConfig['label'] ?? ucfirst($assetSource),
            'metal_type' => $metalType,
            'input_mode' => $inputMode,
            'rate_per_gram' => round($rate, 2),
            'rate_per_gram_display' => '₹'.number_format($rate, 2).' / gm',
            'amount' => $receiveAmount,
            'amount_display' => '₹'.number_format($receiveAmount, 2),
            'weight_grams' => $grams,
            'weight_grams_display' => $weightDisplay.'g',
            'you_will_receive' => $receiveAmount,
            'you_will_receive_display' => '₹'.number_format($receiveAmount, 2),
            'you_will_receive_note' => $fromHoldings
                ? 'Approx at current rate. Final payout uses live rate when admin/auto approves.'
                : 'You will receive (approx) ₹'.number_format($receiveAmount, 2),
            'equivalent_note' => '(Equivalent to '.$weightDisplay.'g '.ucfirst($metalType).')',
            'available_grams' => $availableGrams,
            'available_value' => $availableValue,
            'available_value_display' => '₹'.number_format($availableValue, 2),
            'locked_grams' => $breakdown['locked_grams'],
            'locked_grams_display' => number_format($breakdown['locked_grams'], 2).'g',
            'holding_period_hours' => $holdingPeriodHours,
            'holding_period_message' => $holdingPeriodMessage,
            'hold_bonus_percent' => (float) config('withdraw.hold_bonus_percent', 1),
            'hold_bonus_message' => config('withdraw.hold_bonus_message'),
            'min_amount' => $minAmount,
            'can_withdraw' => $blockReason === null,
            'block_reason' => $blockReason,
            'from_holdings' => $fromHoldings,
            'auto_approve_hours' => $fromHoldings
                ? (int) config('holdings.sell_auto_approve_hours', 2)
                : (int) config('withdraw.auto_approve_hours', 2),
        ];
    }

    /**
     * @return array{total_grams: float, mature_grams: float, locked_grams: float, pending_grams: float, available_grams: float}
     */
    public function availabilityBreakdown(User $user, string $assetSource, ?int $excludeWithdrawalId = null): array
    {
        $assetSource = $this->normalizeAssetSource($assetSource);
        $total = $this->totalHoldingsGrams($user, $assetSource);
        $mature = $this->matureGrams($user, $assetSource);
        $pending = $this->pendingWithdrawalGrams($user, $assetSource, $excludeWithdrawalId);
        $available = max(0, round($mature - $pending, 4));
        $locked = max(0, round($total - $mature, 4));

        return [
            'total_grams' => $total,
            'mature_grams' => $mature,
            'locked_grams' => $locked,
            'pending_grams' => $pending,
            'available_grams' => $available,
        ];
    }

    public function availableGrams(User $user, string $assetSource, ?int $excludeWithdrawalId = null): float
    {
        return $this->availabilityBreakdown($user, $assetSource, $excludeWithdrawalId)['available_grams'];
    }

    protected function totalHoldingsGrams(User $user, string $assetSource): float
    {
        if ($assetSource === 'sig') {
            return round((float) ($this->activeSigPlan($user)?->metal_accumulated_grams ?? 0), 4);
        }

        if ($assetSource === 'silver') {
            return round((float) $user->silver_holdings, 4);
        }

        return round((float) $user->gold_holdings, 4);
    }

    /**
     * Grams eligible for withdrawal (FIFO lots older than holding period, after sells).
     */
    protected function matureGrams(User $user, string $assetSource): float
    {
        $hours = max(0, (int) config('withdraw.holding_period_hours', 48));
        $cutoff = now()->subHours($hours);

        if ($assetSource === 'sig') {
            return $this->matureSigGrams($user, $cutoff);
        }

        return $this->matureInvestmentGrams($user, $assetSource === 'silver' ? 'silver' : 'gold', $cutoff);
    }

    protected function matureInvestmentGrams(User $user, string $metalType, CarbonInterface $cutoff): float
    {
        $buys = Investment::query()
            ->where('user_id', $user->id)
            ->where('metal_type', $metalType)
            ->where('type', 'buy')
            ->where('status', 'completed')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['quantity_grams', 'created_at']);

        $sold = (float) Investment::query()
            ->where('user_id', $user->id)
            ->where('metal_type', $metalType)
            ->where('type', 'sell')
            ->where('status', 'completed')
            ->sum('quantity_grams');

        $sellLeft = $sold;
        $mature = 0.0;

        foreach ($buys as $buy) {
            $qty = (float) $buy->quantity_grams;
            $consumed = min($qty, $sellLeft);
            $sellLeft -= $consumed;
            $remaining = $qty - $consumed;

            if ($remaining <= 0) {
                continue;
            }

            if ($buy->created_at && $buy->created_at->lte($cutoff)) {
                $mature += $remaining;
            }
        }

        return max(0, round($mature, 4));
    }

    protected function matureSigGrams(User $user, CarbonInterface $cutoff): float
    {
        $plan = $this->activeSigPlan($user);
        if (! $plan) {
            return 0.0;
        }

        $lots = SigInstallment::query()
            ->where('sig_plan_id', $plan->id)
            ->where('status', 'success')
            ->orderBy('processed_at')
            ->orderBy('id')
            ->get(['quantity_grams', 'processed_at']);

        $withdrawn = (float) MetalWithdrawal::query()
            ->where('user_id', $user->id)
            ->where('asset_source', 'sig')
            ->whereIn('status', ['approved', 'paid'])
            ->sum('quantity_grams');

        $withdrawLeft = $withdrawn;
        $mature = 0.0;

        foreach ($lots as $lot) {
            $qty = (float) ($lot->quantity_grams ?? 0);
            $consumed = min($qty, $withdrawLeft);
            $withdrawLeft -= $consumed;
            $remaining = $qty - $consumed;

            if ($remaining <= 0) {
                continue;
            }

            $creditedAt = $lot->processed_at;
            if ($creditedAt && $creditedAt->lte($cutoff)) {
                $mature += $remaining;
            }
        }

        // Cap by current plan balance (reconciled).
        $balance = (float) $plan->metal_accumulated_grams;

        return max(0, round(min($mature, $balance), 4));
    }

    protected function pendingWithdrawalGrams(User $user, string $assetSource, ?int $excludeWithdrawalId = null): float
    {
        $query = MetalWithdrawal::query()
            ->where('user_id', $user->id)
            ->where('asset_source', $assetSource)
            ->where('status', 'pending');

        if ($excludeWithdrawalId) {
            $query->where('id', '!=', $excludeWithdrawalId);
        }

        return round((float) $query->sum('quantity_grams'), 4);
    }

    protected function metalTypeForAsset(User $user, string $assetSource): string
    {
        if ($assetSource === 'sig') {
            $plan = $this->activeSigPlan($user);

            return ($plan?->metal_type === 'silver') ? 'silver' : 'gold';
        }

        return $assetSource === 'silver' ? 'silver' : 'gold';
    }

    protected function activeSigPlan(User $user): ?SigPlan
    {
        return SigPlan::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'paused'])
            ->latest('id')
            ->first();
    }

    protected function normalizeAssetSource(string $assetSource): string
    {
        $assetSource = strtolower($assetSource);

        if (! in_array($assetSource, ['gold', 'silver', 'sig'], true)) {
            throw ValidationException::withMessages([
                'asset_source' => ['Asset must be gold, silver, or sig.'],
            ]);
        }

        return $assetSource;
    }

    protected function assertWithdrawalAllowed(User $user): void
    {
        $user->loadMissing('restriction');

        if ($user->restriction?->withdrawal_hold) {
            throw ValidationException::withMessages([
                'withdrawal' => ['Withdrawals are temporarily on hold for your account. Please contact support.'],
            ]);
        }
    }

    /**
     * @return array{bank_name: string, account_holder_name: string, account_number: string, ifsc_code: string, account_number_masked: string, display: string}|null
     */
    protected function bankSnapshot(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $user->loadMissing('kycDetail');
        $kyc = $user->kycDetail;

        if (! $kyc || blank($kyc->account_number) || blank($kyc->ifsc_code)) {
            return null;
        }

        $account = (string) $kyc->account_number;
        $masked = strlen($account) <= 4
            ? $account
            : str_repeat('X', max(0, strlen($account) - 4)).substr($account, -4);

        $bankName = (string) $kyc->bank_name;

        return [
            'bank_name' => $bankName,
            'account_holder_name' => (string) $kyc->account_holder_name,
            'account_number' => $account,
            'account_number_masked' => $masked,
            'ifsc_code' => (string) $kyc->ifsc_code,
            'display' => trim($bankName.' ••••'.substr($account, -4)),
        ];
    }
}
