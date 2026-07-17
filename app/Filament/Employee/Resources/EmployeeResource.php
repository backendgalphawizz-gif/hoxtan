<?php

namespace App\Filament\Employee\Resources;

use App\Filament\Employee\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use App\Support\FilamentFormFields;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = 'Team';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'My Employees';

    protected static ?string $modelLabel = 'Employee';

    protected static ?string $pluralModelLabel = 'Employees';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        /** @var Employee|null $actor */
        $actor = Auth::guard('employee')->user();

        return $actor?->isStaff() ?? false;
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
                            ->helperText('Optional employee code.'),
                        Forms\Components\TextInput::make('role_display')
                            ->label('Role')
                            ->default('Employee')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Role is fixed as Employee.'),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->minLength(8)
                            ->maxLength(255)
                            ->helperText('Login password for the employee panel.'),
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
            ->filters([
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
        /** @var Employee|null $staff */
        $staff = Auth::guard('employee')->user();

        return parent::getEloquentQuery()
            ->where('created_by_employee_id', $staff?->id)
            ->where('role', Employee::ROLE_EMPLOYEE)
            ->with(['department']);
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
