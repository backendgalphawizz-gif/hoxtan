<?php

namespace App\Filament\Resources\WalletTransactionResource\Pages;

use App\Filament\Resources\WalletTransactionResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateWalletTransaction extends CreateRecord
{
    protected static string $resource = WalletTransactionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['balance_after'] = WalletTransactionResource::calculateBalanceAfter(
            (int) $data['user_id'],
            $data['type'],
            (float) $data['amount'],
        );

        $data['created_by'] = Auth::guard('admin')->id();

        if (($data['source'] ?? null) === null) {
            $data['source'] = 'admin';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $user = User::findOrFail($this->record->user_id);
        $user->update(['wallet_balance' => $this->record->balance_after]);
    }
}
