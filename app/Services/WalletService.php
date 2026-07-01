<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function credit(
        User $user,
        float $amount,
        string $source,
        string $description,
        ?int $createdBy = null,
    ): WalletTransaction {
        return $this->apply($user, 'credit', $amount, $source, $description, $createdBy);
    }

    public function debit(
        User $user,
        float $amount,
        string $source,
        string $description,
        ?int $createdBy = null,
    ): WalletTransaction {
        return $this->apply($user, 'debit', $amount, $source, $description, $createdBy);
    }

    protected function apply(
        User $user,
        string $type,
        float $amount,
        string $source,
        string $description,
        ?int $createdBy,
    ): WalletTransaction {
        return DB::transaction(function () use ($user, $type, $amount, $source, $description, $createdBy): WalletTransaction {
            $user->refresh();
            $balance = (float) $user->wallet_balance;
            $balanceAfter = $type === 'credit'
                ? round($balance + $amount, 2)
                : round(max(0, $balance - $amount), 2);

            $transaction = WalletTransaction::create([
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'source' => $source,
                'created_by' => $createdBy,
            ]);

            $user->update(['wallet_balance' => $balanceAfter]);

            return $transaction;
        });
    }
}
