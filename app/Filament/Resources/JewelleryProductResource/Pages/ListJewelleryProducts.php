<?php

namespace App\Filament\Resources\JewelleryProductResource\Pages;

use App\Filament\Resources\JewelleryProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\ActionSize;

class ListJewelleryProducts extends ListRecords
{
    protected static string $resource = JewelleryProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Product')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->size(ActionSize::Large)
                ->extraAttributes(['class' => 'gs-primary-action-btn']),
        ];
    }
}
