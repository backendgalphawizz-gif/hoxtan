<?php

namespace App\Filament\Resources\WalletTransactionResource\Pages;

use App\Filament\Exports\WalletTransactionExporter;
use App\Filament\Resources\WalletTransactionResource;
use App\Support\FilamentExportActions;
use Filament\Resources\Pages\ListRecords;

class ListWalletTransactions extends ListRecords
{
    protected static string $resource = WalletTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FilamentExportActions::headerExport(WalletTransactionExporter::class, 'wallet_transactions'),
        ];
    }
}
