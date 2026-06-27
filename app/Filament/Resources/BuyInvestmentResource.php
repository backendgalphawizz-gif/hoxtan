<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BuyInvestmentResource\Pages;
use App\Support\NavigationBadgeCounts;

class BuyInvestmentResource extends InvestmentResource
{
    protected static bool $shouldRegisterNavigation = true;

    protected static ?int $navigationSort = 1;

    protected static function transactionType(): string
    {
        return 'buy';
    }

    protected static function resolveNavigationLabel(): string
    {
        return 'Buy Transactions';
    }

    protected static function resolveNavigationIcon(): string
    {
        return 'heroicon-o-arrow-trending-up';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBuyInvestments::route('/'),
            'create' => Pages\CreateBuyInvestment::route('/create'),
            'view' => Pages\ViewBuyInvestment::route('/{record}'),
            'edit' => Pages\EditBuyInvestment::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return NavigationBadgeCounts::format(NavigationBadgeCounts::pendingBuyTransactions());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
