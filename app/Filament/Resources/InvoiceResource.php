<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use App\Support\FilamentDateFilters;
use App\Support\FilamentTableActions;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'investment', 'jewelleryOrder']);
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
                Tables\Columns\BadgeColumn::make('source')
                    ->label('Type')
                    ->getStateUsing(fn (Invoice $record): string => $record->sourceType())
                    ->formatStateUsing(fn (string $state): string => $state === 'jewellery' ? 'Jewellery' : 'Metal')
                    ->colors([
                        'warning' => 'investment',
                        'success' => 'jewellery',
                    ]),
                Tables\Columns\TextColumn::make('reference')
                    ->label('Reference')
                    ->getStateUsing(fn (Invoice $record): string => $record->sourceReference() ?? '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $inner) use ($search): void {
                            $inner->whereHas('investment', fn (Builder $q) => $q->where('reference_id', 'like', "%{$search}%"))
                                ->orWhereHas('jewelleryOrder', fn (Builder $q) => $q->where('order_number', 'like', "%{$search}%"));
                        });
                    }),
                Tables\Columns\BadgeColumn::make('metal_type')
                    ->colors(['warning' => 'gold', 'gray' => 'silver'])
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('quantity_grams')
                    ->label('Qty (g)')
                    ->grams(4)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->inr()
                    ->sortable(),
                Tables\Columns\TextColumn::make('issued_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('source')
                    ->label('Type')
                    ->placeholder('All types')
                    ->options([
                        'investment' => 'Metal',
                        'jewellery' => 'Jewellery',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'jewellery' => $query->whereNotNull('jewellery_order_id'),
                            'investment' => $query->whereNotNull('investment_id'),
                            default => $query,
                        };
                    }),
                Tables\Filters\SelectFilter::make('metal_type')
                    ->label('Metal')
                    ->placeholder('All metals')
                    ->options(['gold' => 'Gold', 'silver' => 'Silver']),
                FilamentDateFilters::tableFilter('issued_date', 'issued_at', 'Issued Date'),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
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
