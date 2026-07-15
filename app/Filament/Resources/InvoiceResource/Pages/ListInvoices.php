<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    public function getSubheading(): ?string
    {
        return 'Invoices auto-generated for completed metal buys and fully paid jewellery orders.';
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(fn (): int => $this->getModel()::query()->count()),
            'metal' => Tab::make('Metal')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNotNull('investment_id'))
                ->badge(fn (): int => $this->getModel()::query()->whereNotNull('investment_id')->count()),
            'jewellery' => Tab::make('Jewellery')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNotNull('jewellery_order_id'))
                ->badge(fn (): int => $this->getModel()::query()->whereNotNull('jewellery_order_id')->count()),
        ];
    }
}
