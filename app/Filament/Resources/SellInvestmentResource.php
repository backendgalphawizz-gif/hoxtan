<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SellInvestmentResource\Pages;
use App\Support\NavigationBadgeCounts;

class SellInvestmentResource extends InvestmentResource
{
    protected static bool $shouldRegisterNavigation = true;

    protected static ?int $navigationSort = 2;

    protected static function transactionType(): string
    {
        return 'sell';
    }

    protected static function resolveNavigationLabel(): string
    {
        return 'Sell Transactions';
    }

    protected static function resolveNavigationIcon(): string
    {
        return 'heroicon-o-arrow-trending-down';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSellInvestments::route('/'),
            'create' => Pages\CreateSellInvestment::route('/create'),
            'view' => Pages\ViewSellInvestment::route('/{record}'),
            'edit' => Pages\EditSellInvestment::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return NavigationBadgeCounts::format(NavigationBadgeCounts::pendingSellTransactions());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
