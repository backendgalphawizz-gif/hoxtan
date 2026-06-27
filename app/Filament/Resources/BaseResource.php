<?php

namespace App\Filament\Resources;

use App\Support\FilamentTableActions;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

abstract class BaseResource extends Resource
{
    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = null;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50, 100])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->emptyStateHeading('No records found')
            ->emptyStateDescription('Create a new record to get started.')
            ->emptyStateIcon('heroicon-o-inbox')
            ->actions([
                FilamentTableActions::view(),
                FilamentTableActions::edit(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
