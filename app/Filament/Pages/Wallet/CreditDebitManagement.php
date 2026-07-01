<?php

namespace App\Filament\Pages\Wallet;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\WalletTransactionResource;
use App\Models\User;
use App\Models\WalletTransaction;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Auth;

class CreditDebitManagement extends Page implements HasForms
{
    use InteractsWithAdminPermissions;
    use InteractsWithForms;

    protected static function adminPermissionModule(): string
    {
        return 'wallet_credit_debit';
    }

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $navigationGroup = 'Wallet Management';

    protected static ?string $navigationLabel = 'Credit / Debit Management';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'admin.wallet.credit-debit';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'source' => 'admin',
        ]);
    }

    public function getSubheading(): ?string
    {
        return 'Add wallet credits or debits for user accounts.';
    }

    public function form(Form $form): Form
    {
        return WalletTransactionResource::form(
            $form->model(WalletTransaction::class),
        )->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $data['balance_after'] = WalletTransactionResource::calculateBalanceAfter(
            (int) $data['user_id'],
            $data['type'],
            (float) $data['amount'],
        );
        $data['created_by'] = Auth::guard('admin')->id();
        $data['source'] = $data['source'] ?? 'admin';

        $record = WalletTransaction::create($data);

        User::findOrFail($record->user_id)->update([
            'wallet_balance' => $record->balance_after,
        ]);

        Notification::make()
            ->title('Wallet transaction recorded')
            ->success()
            ->send();

        $this->form->fill(['source' => 'admin']);
    }
}
