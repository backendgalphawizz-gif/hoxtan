<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\ReferralResource\Pages;
use App\Models\Referral;
use App\Support\FilamentDateFilters;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReferralResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static ?string $model = Referral::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Refer & Earn';

    protected static ?string $modelLabel = 'Referral';

    protected static ?string $pluralModelLabel = 'Referrals';

    protected static function adminPermissionModule(): string
    {
        return 'refer_and_earn';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('referrer.name')
                    ->label('Referrer')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('referrer.phone')
                    ->label('Referrer Mobile')
                    ->searchable(),
                Tables\Columns\TextColumn::make('referrer.referral_code')
                    ->label('Code Used')
                    ->copyable(),
                Tables\Columns\TextColumn::make('referee.name')
                    ->label('New User')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('referee.phone')
                    ->label('New User Mobile')
                    ->searchable(),
                Tables\Columns\TextColumn::make('bonus_amount')
                    ->label('Bonus (₹)')
                    ->inr()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'credited',
                        'gray' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('credited_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'credited' => 'Credited',
                        'cancelled' => 'Cancelled',
                    ]),
                FilamentDateFilters::tableFilter('created_date', 'created_at', 'Created Date'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReferrals::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
