<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use App\Support\FilamentFormFields;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class EmployeeResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Employees';

    protected static ?string $modelLabel = 'Employee';

    protected static ?string $pluralModelLabel = 'Employees';

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
                            ->label('Employee Code')
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
                            ->required(),
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
                    ->label('Code')
                    ->badge()
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Department')
                    ->badge()
                    ->sortable()
                    ->searchable(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
