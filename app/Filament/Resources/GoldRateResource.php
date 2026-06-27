<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GoldRateResource\Pages;

class GoldRateResource extends MetalRateResource
{
    protected static ?string $navigationGroup = 'Gold Rate Management';

    protected static bool $shouldRegisterNavigation = true;

    protected static ?int $navigationSort = 3;

    protected static function metalType(): string
    {
        return 'gold';
    }

    protected static function resolveNavigationLabel(): string
    {
        return 'Rate History';
    }

    protected static function resolveNavigationIcon(): string
    {
        return 'heroicon-o-clock';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoldRates::route('/'),
            'create' => Pages\CreateGoldRate::route('/create'),
            'edit' => Pages\EditGoldRate::route('/{record}/edit'),
        ];
    }
}
