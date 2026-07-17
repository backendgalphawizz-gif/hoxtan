<?php

namespace App\Filament\Employee\Resources;

use App\Filament\Employee\Resources\UserResource\Pages;
use App\Models\Employee;
use App\Models\User;
use App\Support\FilamentFormFields;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Users';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'My Users';

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'Users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Registration Details')
                    ->schema([
                        FilamentFormFields::name()
                            ->required(),
                        FilamentFormFields::mobile()
                            ->required()
                            ->unique(ignoreRecord: true),
                        Forms\Components\DatePicker::make('date_of_birth')
                            ->label('Date of Birth')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->maxDate(now()->subYears(18))
                            ->minDate(now()->subYears(100))
                            ->helperText('User must be at least 18 years old.'),
                        FilamentFormFields::mpin()
                            ->required(fn (string $operation) => $operation === 'create')
                            ->visible(fn (string $operation) => $operation === 'create'),
                        FilamentFormFields::mpin('mpin', 'New MPIN', false)
                            ->helperText('Leave blank to keep current MPIN.')
                            ->visible(fn (string $operation) => $operation === 'edit'),
                        Forms\Components\TextInput::make('referral_code_input')
                            ->label('Referral Code (optional)')
                            ->maxLength(12)
                            ->visible(fn (string $operation) => $operation === 'create')
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('referral_code')
                            ->label('User Referral Code')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $operation) => $operation === 'edit'),
                        Forms\Components\TextInput::make('created_by_employee_code')
                            ->label('Created By (Employee Code)')
                            ->default(fn (): ?string => Auth::guard('employee')->user()?->employee_code)
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $operation) => $operation === 'create')
                            ->helperText('Your employee code is recorded as the creator.'),
                    ])->columns(2),

                Forms\Components\Section::make('Nominee Details')
                    ->description('Nominee information for the user profile.')
                    ->schema([
                        FilamentFormFields::fullName('nominee_name', 'Nominee Name', false),
                        FilamentFormFields::relation('nominee_relation', 'Relation', false),
                        FilamentFormFields::mobile('nominee_phone', 'Nominee Mobile', false),
                        Forms\Components\DatePicker::make('nominee_date_of_birth')
                            ->label('Nominee Date of Birth')
                            ->native(false)
                            ->maxDate(now()),
                    ])->columns(2),

                Forms\Components\Section::make('Account Status')
                    ->schema([
                        Forms\Components\Hidden::make('role')
                            ->default('user')
                            ->dehydrated(),
                        Forms\Components\Select::make('kyc_status')
                            ->options([
                                'pending' => 'Pending',
                                'submitted' => 'Submitted',
                                'under_review' => 'Under Review',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->required()
                            ->default('pending'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
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
                    ->boolean()
                    ->trueIcon('heroicon-o-no-symbol')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kyc_status')
                    ->label('KYC Status')
                    ->options([
                        'pending' => 'Pending',
                        'submitted' => 'Submitted',
                        'under_review' => 'Under Review',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\TernaryFilter::make('is_blocked')
                    ->label('Blocked'),
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
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var Employee|null $employee */
        $employee = Auth::guard('employee')->user();

        return parent::getEloquentQuery()
            ->where('created_by_employee_id', $employee?->id);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
