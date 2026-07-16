<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\HoldingCertificateResource\Pages;
use App\Models\HoldingCertificate;
use App\Support\FilamentDateFilters;
use App\Support\FilamentTableActions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HoldingCertificateResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static ?string $model = HoldingCertificate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Investment Management';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Holding Certificates';

    protected static ?string $modelLabel = 'Holding Certificate';

    protected static ?string $pluralModelLabel = 'Holding Certificates';

    protected static function adminPermissionModule(): string
    {
        return 'holding_certificates';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'investment']);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Certificate')
                    ->schema([
                        Infolists\Components\TextEntry::make('certificate_number')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Client'),
                        Infolists\Components\TextEntry::make('user.phone')
                            ->label('Mobile'),
                        Infolists\Components\TextEntry::make('account_holder_name')
                            ->label('Account Holder'),
                        Infolists\Components\TextEntry::make('metal_type')
                            ->badge()
                            ->colors(['warning' => 'gold', 'gray' => 'silver']),
                        Infolists\Components\TextEntry::make('holding_grams')
                            ->label('Holding')
                            ->suffix(' g'),
                        Infolists\Components\TextEntry::make('purity'),
                        Infolists\Components\TextEntry::make('investment.reference_id')
                            ->label('Buy Reference')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('issued_at')
                            ->dateTime('d M Y, h:i A'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('certificate_number')
                    ->label('Certificate No.')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.phone')
                    ->label('Mobile')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('metal_type')
                    ->colors(['warning' => 'gold', 'gray' => 'silver']),
                Tables\Columns\TextColumn::make('holding_grams')
                    ->label('Holding (g)')
                    ->grams(4)
                    ->sortable(),
                Tables\Columns\TextColumn::make('purity'),
                Tables\Columns\TextColumn::make('investment.reference_id')
                    ->label('Buy Ref')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('issued_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('metal_type')
                    ->label('Metal')
                    ->options(['gold' => 'Gold', 'silver' => 'Silver']),
                FilamentDateFilters::tableFilter('issued_date', 'issued_at', 'Issued Date'),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(2)
            ->actions([
                FilamentTableActions::view(),
                FilamentTableActions::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->tooltip('Download Certificate')
                    ->url(fn (HoldingCertificate $record): ?string => route('admin.certificates.download', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (HoldingCertificate $record): bool => filled($record->file_path)
                        || $record->investment !== null),
            ])
            ->defaultSort('issued_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHoldingCertificates::route('/'),
            'view' => Pages\ViewHoldingCertificate::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
