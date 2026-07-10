<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\DriverResource\Pages;
use App\Models\Driver;
use App\Support\FilamentFormFields;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DriverResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static function adminPermissionModule(): string
    {
        return 'drivers';
    }

    protected static ?string $model = Driver::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Delivery Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Drivers';

    protected static ?string $modelLabel = 'Driver';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Driver Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(100),
                        FilamentFormFields::mobile('phone', 'Phone', true),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('profile_image')
                            ->label('Profile Image')
                            ->image()
                            ->directory('driver-profiles')
                            ->maxSize(2048),
                        Forms\Components\Select::make('vehicle_type')
                            ->options(Driver::vehicleTypeOptions())
                            ->default('bike')
                            ->required(),
                        Forms\Components\TextInput::make('vehicle_number')
                            ->label('Vehicle Number')
                            ->maxLength(30),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Contact & Address')
                    ->schema([
                        Forms\Components\Textarea::make('primary_residence')
                            ->label('Address')
                            ->required()
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                        FilamentFormFields::mobile('emergency_no', 'Emergency No', true),
                    ])->columns(2),

                Forms\Components\Section::make('Documents & Licence')
                    ->schema([
                        Forms\Components\FileUpload::make('registration_card_image')
                            ->label('Registration Card Image')
                            ->image()
                            ->directory('driver-documents/registration')
                            ->maxSize(4096)
                            ->required(),
                        Forms\Components\TextInput::make('licence_no')
                            ->label('Licence No')
                            ->required()
                            ->maxLength(30),
                        Forms\Components\FileUpload::make('licence_image')
                            ->label('Licence Image')
                            ->image()
                            ->directory('driver-documents/licence')
                            ->maxSize(4096)
                            ->required(),
                        Forms\Components\FileUpload::make('aadhaar_front_image')
                            ->label('Aadhaar Card Image (Front)')
                            ->image()
                            ->directory('driver-documents/aadhaar')
                            ->maxSize(4096)
                            ->required(),
                        Forms\Components\FileUpload::make('aadhaar_back_image')
                            ->label('Aadhaar Card Image (Back)')
                            ->image()
                            ->directory('driver-documents/aadhaar')
                            ->maxSize(4096)
                            ->required(),
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
                    ->searchable(),
                Tables\Columns\TextColumn::make('vehicle_type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Driver::vehicleTypeOptions()[$state ?? ''] ?? $state),
                Tables\Columns\TextColumn::make('vehicle_number')
                    ->label('Vehicle No.')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('licence_no')
                    ->label('Licence No.')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                FilamentTableActions::view(),
                FilamentTableActions::edit(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDrivers::route('/'),
            'create' => Pages\CreateDriver::route('/create'),
            'view' => Pages\ViewDriver::route('/{record}'),
            'edit' => Pages\EditDriver::route('/{record}/edit'),
        ];
    }
}
