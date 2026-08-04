<?php

namespace App\Filament\Resources\HoldingCertificateResource\Pages;

use App\Filament\Resources\HoldingCertificateResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListHoldingCertificates extends ListRecords
{
    protected static string $resource = HoldingCertificateResource::class;

    public function getSubheading(): ?string
    {
        return 'Proof-of-holdings certificates issued automatically when a metal buy transaction is completed.';
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(fn (): int => $this->getModel()::query()->count()),
            'gold' => Tab::make('Gold')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('metal_type', 'gold'))
                ->badge(fn (): int => $this->getModel()::query()->where('metal_type', 'gold')->count()),
            'silver' => Tab::make('Silver')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('metal_type', 'silver'))
                ->badge(fn (): int => $this->getModel()::query()->where('metal_type', 'silver')->count()),
        ];
    }
}
