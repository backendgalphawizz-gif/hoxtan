<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Filament\Resources\EmployeeResource;
use App\Models\Employee;
use App\Support\FilamentTableActions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'createdEmployees';

    protected static ?string $title = 'Employees';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Employee && $ownerRecord->isStaff();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('role', Employee::ROLE_EMPLOYEE))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('employee_code')
                    ->label('Code')
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_users_count')
                    ->label('Users')
                    ->counts('createdUsers'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->headerActions([])
            ->actions([
                FilamentTableActions::view()
                    ->url(fn (Employee $record): string => EmployeeResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([])
            ->defaultSort('name');
    }
}
