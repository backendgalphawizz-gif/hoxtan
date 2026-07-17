<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\JewelleryOrder;
use App\Models\MetalWithdrawal;
use App\Models\OldGoldBooking;
use App\Models\Redemption;
use App\Models\SigInstallment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\AccountTransactionPayload;
use Illuminate\Support\Collection;

class AccountActivityService
{
    /**
     * @return array{transactions: list<array<string, mixed>>, pagination: array<string, int|bool>}
     */
    public function listTransactions(
        User $user,
        string $filter = 'all',
        int $page = 1,
        int $perPage = 20,
        ?string $metalType = null,
        ?string $category = null,
    ): array {
        $metalFilter = $metalType
            ?? (in_array($filter, ['gold', 'silver'], true) ? $filter : null);

        // Explicit category wins; otherwise use filter when it is not a metal type.
        $categoryFilter = filled($category) && $category !== 'all'
            ? $category
            : (in_array($filter, ['gold', 'silver'], true) ? 'all' : $filter);

        // Metal filters skip pure wallet rows (no metal_type).
        if ($metalFilter !== null && $categoryFilter === 'all') {
            $transactions = $this->collectTransactions($user, 'all', includeWallet: false);
        } else {
            $transactions = $this->collectTransactions($user, $categoryFilter);
        }

        if ($metalFilter !== null) {
            $transactions = $transactions
                ->filter(fn (array $item) => ($item['metal_type'] ?? null) === $metalFilter)
                ->values();
        }

        $transactions = $transactions
            ->sortByDesc(fn (array $item) => $item['occurred_at'] ?? '')
            ->values();

        $total = $transactions->count();
        $offset = max(0, ($page - 1) * $perPage);
        $slice = $transactions->slice($offset, $perPage)->values();

        return [
            'transactions' => $slice->all(),
            'filter' => $filter,
            'category' => filled($category) && $category !== 'all' ? $category : (
                in_array($filter, ['gold', 'silver'], true) ? null : $categoryFilter
            ),
            'metal_type' => $metalFilter,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'has_more' => ($offset + $slice->count()) < $total,
                'showing' => $slice->count(),
            ],
        ];
    }

    public function findTransaction(User $user, string $transactionId): ?array
    {
        [$sourceType, $sourceId] = $this->parseTransactionId($transactionId);

        if ($sourceType === null || $sourceId === null) {
            return null;
        }

        return match ($sourceType) {
            'investment' => $this->findInvestment($user, $sourceId),
            'wallet' => $this->findWallet($user, $sourceId),
            'sig' => $this->findSig($user, $sourceId),
            'jewellery_order' => $this->findJewelleryOrder($user, $sourceId),
            'old_gold' => $this->findOldGold($user, $sourceId),
            'redemption' => $this->findRedemption($user, $sourceId),
            'holdings_sell' => $this->findHoldingsSell($user, $sourceId),
            default => null,
        };
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function collectTransactions(User $user, string $filter, bool $includeWallet = true): Collection
    {
        $items = collect();

        if ($this->includesFilter($filter, ['all', 'buy', 'sell'])) {
            $linkedSellInvestmentIds = MetalWithdrawal::query()
                ->where('user_id', $user->id)
                ->where('from_holdings', true)
                ->whereNotNull('investment_id')
                ->pluck('investment_id')
                ->all();

            $investments = $user->investments()
                ->with(['holdingCertificate', 'invoice'])
                ->latest('id')
                ->limit(100)
                ->get();

            foreach ($investments as $investment) {
                // Avoid duplicating holdings sells (shown via holdings_sell source).
                if ($investment->type === 'sell' && in_array($investment->id, $linkedSellInvestmentIds, true)) {
                    continue;
                }

                $payload = AccountTransactionPayload::fromInvestment($investment);

                if ($filter === 'buy' && $payload['category'] !== 'buy') {
                    continue;
                }

                if ($filter === 'sell' && $payload['category'] !== 'sell') {
                    continue;
                }

                $items->push($payload);
            }
        }

        if ($this->includesFilter($filter, ['all', 'sell', 'holdings'])) {
            MetalWithdrawal::query()
                ->where('user_id', $user->id)
                ->where(function ($q): void {
                    $q->where('from_holdings', true)
                        ->orWhereNotNull('source_lot_id');
                })
                ->latest('id')
                ->limit(100)
                ->get()
                ->each(fn (MetalWithdrawal $withdrawal) => $items->push(
                    AccountTransactionPayload::fromHoldingsSell($withdrawal),
                ));
        }

        if ($includeWallet && $this->includesFilter($filter, ['all', 'wallet'])) {
            $user->walletTransactions()->latest('id')->limit(100)->get()
                ->each(fn (WalletTransaction $transaction) => $items->push(
                    AccountTransactionPayload::fromWallet($transaction),
                ));
        }

        if ($this->includesFilter($filter, ['all', 'sig'])) {
            SigInstallment::query()
                ->where('user_id', $user->id)
                ->with('plan')
                ->latest('id')
                ->limit(100)
                ->get()
                ->each(fn (SigInstallment $installment) => $items->push(
                    AccountTransactionPayload::fromSigInstallment($installment),
                ));
        }

        if ($this->includesFilter($filter, ['all', 'jewellery', 'buy'])) {
            $user->jewelleryOrders()
                ->where('status', '!=', 'cart')
                ->with(['items.product', 'invoice'])
                ->latest('id')
                ->limit(100)
                ->get()
                ->each(fn (JewelleryOrder $order) => $items->push(
                    AccountTransactionPayload::fromJewelleryOrder($order),
                ));
        }

        if ($this->includesFilter($filter, ['all', 'sell'])) {
            $user->oldGoldBookings()->latest('id')->limit(100)->get()
                ->each(fn (OldGoldBooking $booking) => $items->push(
                    AccountTransactionPayload::fromOldGoldBooking($booking),
                ));
        }

        if ($this->includesFilter($filter, ['all', 'redemption', 'sell'])) {
            $user->redemptions()->latest('id')->limit(100)->get()
                ->each(fn (Redemption $redemption) => $items->push(
                    AccountTransactionPayload::fromRedemption($redemption),
                ));
        }

        return $items;
    }

    protected function findInvestment(User $user, int $id): ?array
    {
        $investment = $user->investments()->with(['holdingCertificate', 'invoice'])->find($id);

        return $investment ? AccountTransactionPayload::fromInvestment($investment) : null;
    }

    protected function findWallet(User $user, int $id): ?array
    {
        $transaction = $user->walletTransactions()->find($id);

        return $transaction ? AccountTransactionPayload::fromWallet($transaction) : null;
    }

    protected function findSig(User $user, int $id): ?array
    {
        $installment = SigInstallment::query()
            ->where('user_id', $user->id)
            ->with('plan')
            ->find($id);

        return $installment ? AccountTransactionPayload::fromSigInstallment($installment) : null;
    }

    protected function findJewelleryOrder(User $user, int $id): ?array
    {
        $order = $user->jewelleryOrders()
            ->where('status', '!=', 'cart')
            ->with(['items.product', 'invoice'])
            ->find($id);

        return $order ? AccountTransactionPayload::fromJewelleryOrder($order) : null;
    }

    protected function findOldGold(User $user, int $id): ?array
    {
        $booking = $user->oldGoldBookings()->find($id);

        return $booking ? AccountTransactionPayload::fromOldGoldBooking($booking) : null;
    }

    protected function findRedemption(User $user, int $id): ?array
    {
        $redemption = $user->redemptions()->find($id);

        return $redemption ? AccountTransactionPayload::fromRedemption($redemption) : null;
    }

    protected function findHoldingsSell(User $user, int $id): ?array
    {
        $withdrawal = MetalWithdrawal::query()
            ->where('user_id', $user->id)
            ->where(function ($q): void {
                $q->where('from_holdings', true)
                    ->orWhereNotNull('source_lot_id');
            })
            ->find($id);

        return $withdrawal ? AccountTransactionPayload::fromHoldingsSell($withdrawal) : null;
    }

    /**
     * Sell-only holdings transactions for the holdings screen.
     *
     * @return array{transactions: list<array<string, mixed>>, pagination: array<string, int|bool>}
     */
    public function listHoldingsSellTransactions(
        User $user,
        int $page = 1,
        int $perPage = 20,
        ?string $metalType = null,
    ): array {
        $query = MetalWithdrawal::query()
            ->where('user_id', $user->id)
            ->where(function ($q): void {
                $q->where('from_holdings', true)
                    ->orWhereNotNull('source_lot_id');
            })
            ->when($metalType, fn ($q) => $q->where('metal_type', $metalType))
            ->latest('id');

        $total = (clone $query)->count();
        $items = $query
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (MetalWithdrawal $withdrawal) => AccountTransactionPayload::fromHoldingsSell($withdrawal))
            ->values();

        return [
            'transactions' => $items->all(),
            'filter' => 'sell',
            'metal_type' => $metalType,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'has_more' => ($page * $perPage) < $total,
                'showing' => $items->count(),
            ],
        ];
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    protected function parseTransactionId(string $transactionId): array
    {
        if (! str_contains($transactionId, ':')) {
            return [null, null];
        }

        [$sourceType, $sourceId] = explode(':', $transactionId, 2);

        if (! filled($sourceType) || ! ctype_digit($sourceId)) {
            return [null, null];
        }

        return [$sourceType, (int) $sourceId];
    }

    /**
     * @param  list<string>  $allowed
     */
    protected function includesFilter(string $filter, array $allowed): bool
    {
        return in_array($filter, $allowed, true);
    }
}
