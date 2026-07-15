<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\BlockedPincodeResource\Pages;
use App\Models\BlockedPincode;
use App\Support\FilamentFormFields;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BlockedPincodeResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static function adminPermissionModule(): string
    {
        return 'blocked_pincodes';
    }

    protected static ?string $model = BlockedPincode::class;

    protected static ?string $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $navigationGroup = 'Delivery Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Blocked Pincodes';

    protected static ?string $modelLabel = 'Blocked Pincode';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Pincode Details')
                    ->schema([
                        FilamentFormFields::pincode(required: true),
                        FilamentFormFields::city(required: false),
                        FilamentFormFields::state(required: false),
                        Forms\Components\TextInput::make('reason')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Blocked')
                            ->helperText('When active, this pincode is blocked for delivery.')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pincode')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('state')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('reason')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Blocked'),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Added By')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
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
            ->defaultSort('pincode');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlockedPincodes::route('/'),
            'create' => Pages\CreateBlockedPincode::route('/create'),
            'edit' => Pages\EditBlockedPincode::route('/{record}/edit'),
        ];
    }
}
