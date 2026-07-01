<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use App\Support\FilamentDateFilters;
use App\Support\FilamentTableActions;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class InvoiceResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Investment Management';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Purchase Invoices';

    protected static ?string $modelLabel = 'Invoice';

    protected static function adminPermissionModule(): string
    {
        return 'invoices';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('investment.reference_id')
                    ->label('Investment Ref')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('metal_type')
                    ->colors(['warning' => 'gold', 'gray' => 'silver']),
                Tables\Columns\TextColumn::make('quantity_grams')
                    ->label('Qty (g)')
                    ->grams(4),
                Tables\Columns\TextColumn::make('total_amount')
                    ->inr()
                    ->sortable(),
                Tables\Columns\TextColumn::make('issued_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('metal_type')
                    ->options(['gold' => 'Gold', 'silver' => 'Silver']),
                FilamentDateFilters::tableFilter('issued_date', 'issued_at', 'Issued Date'),
            ])
            ->actions([
                FilamentTableActions::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->tooltip('Download Invoice')
                    ->url(fn (Invoice $record): ?string => $record->file_path && Storage::disk('local')->exists($record->file_path)
                        ? route('admin.invoices.download', $record)
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (Invoice $record): bool => filled($record->file_path)),
            ])
            ->defaultSort('issued_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
