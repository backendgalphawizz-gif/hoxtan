<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\Employee;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'createdUsers';

    protected static ?string $title = 'Users';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Employee && $ownerRecord->isTeamEmployee();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Mobile')
                    ->searchable(),
                Tables\Columns\TextColumn::make('referral_code')
                    ->label('Referral')
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('kyc_status')
                    ->label('KYC')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state)))
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'under_review', 'submitted' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_blocked')
                    ->label('Blocked')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }
}
