<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers;
use App\Models\Employee;
use App\Support\FilamentFormFields;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class EmployeeResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static ?string $model = Employee::class;

    protected static ?string $slug = 'staff';

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Staff';

    protected static ?string $modelLabel = 'Staff';

    protected static ?string $pluralModelLabel = 'Staff';

    protected static function adminPermissionModule(): string
    {
        return 'employees';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Details')
                    ->schema([
                        FilamentFormFields::name('name', 'Full Name', true, 120)
                            ->required(),
                        FilamentFormFields::email()
                            ->required()
                            ->unique(ignoreRecord: true),
                        FilamentFormFields::mobile('phone', 'Phone', false)
                            ->nullable()
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('employee_code')
                            ->label('Staff Code')
                            ->maxLength(32)
                            ->unique(ignoreRecord: true)
                            ->helperText('Optional staff code.'),
                        Forms\Components\Select::make('department_id')
                            ->label('Department')
                            ->relationship(
                                'department',
                                'name',
                                fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (?Employee $record): bool => $record?->isTeamEmployee() ?? false)
                            ->dehydrated(),
                        Forms\Components\Placeholder::make('role_display')
                            ->label('Role')
                            ->content(fn (?Employee $record): string => $record?->isTeamEmployee() ? 'Employee' : 'Staff')
                            ->visibleOn('edit'),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->minLength(8)
                            ->maxLength(255)
                            ->helperText('Used to log in at /employee'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Basic Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('email'),
                        Infolists\Components\TextEntry::make('phone')->placeholder('—'),
                        Infolists\Components\TextEntry::make('employee_code')
                            ->label('Code')
                            ->badge()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('department.name')
                            ->label('Department')
                            ->badge(),
                        Infolists\Components\TextEntry::make('role')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => $state === Employee::ROLE_EMPLOYEE ? 'Employee' : 'Staff')
                            ->color(fn (string $state): string => $state === Employee::ROLE_EMPLOYEE ? 'info' : 'warning'),
                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime('d M Y, h:i A'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('employee_code')
                    ->label('Staff Code')
                    ->badge()
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Department')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_employees_count')
                    ->label('Employees')
                    ->counts('createdEmployees')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                FilamentTableActions::view(),
                FilamentTableActions::edit(),
                FilamentTableActions::delete(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['department']);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EmployeesRelationManager::class,
            RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'view' => Pages\ViewEmployee::route('/{record}'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
