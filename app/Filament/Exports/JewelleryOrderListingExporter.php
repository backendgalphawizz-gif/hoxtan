<?php

namespace App\Filament\Exports;

use App\Models\JewelleryOrderListing;
use App\Support\SellJewelleryPayload;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class JewelleryOrderListingExporter extends Exporter
{
    protected static ?string $model = JewelleryOrderListing::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('listing_type')
                ->label('Type')
                ->formatStateUsing(fn (?string $state): string => $state === 'sell' ? 'Sell' : 'Buy'),
            ExportColumn::make('reference_number')
                ->label('Order #'),
            ExportColumn::make('user.name')
                ->label('Customer'),
            ExportColumn::make('user.phone')
                ->label('Phone'),
            ExportColumn::make('product_summary')
                ->label('Product')
                ->state(fn (JewelleryOrderListing $record): string => $record->productSummary()),
            ExportColumn::make('status')
                ->label('Status')
                ->state(function (JewelleryOrderListing $record): string {
                    return $record->isSell()
                        ? SellJewelleryPayload::statusLabel($record->status)
                        : ucfirst((string) $record->status);
                }),
            ExportColumn::make('amount')
                ->label('Total'),
            ExportColumn::make('payment_mode')
                ->label('Payment')
                ->formatStateUsing(fn (?string $state): string => match ($state) {
                    'emi' => 'EMI',
                    'full' => 'Full',
                    default => $state ? (string) $state : '—',
                }),
            ExportColumn::make('driver.name')
                ->label('Driver'),
            ExportColumn::make('driver.phone')
                ->label('Driver Phone'),
            ExportColumn::make('created_at')
                ->label('Ordered At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Jewellery orders export completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
