<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SilverRateResource\Pages;

class SilverRateResource extends MetalRateResource
{
    protected static ?string $navigationGroup = 'Silver Rate Management';

    protected static bool $shouldRegisterNavigation = true;

    protected static ?int $navigationSort = 3;

    protected static function adminPermissionModule(): string
    {
        return 'silver_rate_history';
    }

    protected static function metalType(): string
    {
        return 'silver';
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
            'index' => Pages\ListSilverRates::route('/'),
            'create' => Pages\CreateSilverRate::route('/create'),
            'edit' => Pages\EditSilverRate::route('/{record}/edit'),
        ];
    }
}
