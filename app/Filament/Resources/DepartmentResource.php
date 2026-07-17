<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\DepartmentResource\Pages;
use App\Models\Department;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DepartmentResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static function adminPermissionModule(): string
    {
        return 'departments';
    }

    protected static ?string $model = Department::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Departments';

    protected static ?string $modelLabel = 'Department';

    protected static ?string $pluralModelLabel = 'Departments';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Department Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(120)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText('Optional short code (e.g. HR, FIN, OPS).'),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
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
                Tables\Columns\TextColumn::make('code')
                    ->badge()
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'edit' => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }
}
