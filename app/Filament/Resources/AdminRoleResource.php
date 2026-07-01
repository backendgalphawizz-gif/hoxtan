<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdminRoleResource\Pages;
use App\Models\AdminRole;
use App\Support\AdminPermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AdminRoleResource extends Resource
{
    protected static ?string $model = AdminRole::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Roles & Permissions';

    protected static ?string $modelLabel = 'Role';

    protected static ?string $pluralModelLabel = 'Roles';

    public static function canAccess(): bool
    {
        return AdminPermissions::canViewModule('admin_roles');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Role Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state, ?AdminRole $record): void {
                                if ($record !== null) {
                                    return;
                                }

                                $set('slug', Str::slug((string) $state));
                            }),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->alphaDash(),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Role is active')
                            ->default(true),
                        Forms\Components\Toggle::make('is_super_admin')
                            ->label('Super Admin (full access)')
                            ->helperText('Super admins can access every module and manage other admins.')
                            ->live()
                            ->disabled(fn (?AdminRole $record): bool => (bool) $record?->is_super_admin),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Module Permissions')
                    ->description('Choose which admin tabs and actions this role can use.')
                    ->schema([
                        Forms\Components\Hidden::make('permissions')
                            ->default(AdminPermissions::emptyMatrix())
                            ->dehydrated(),
                        Forms\Components\View::make('admin.roles.permissions-matrix')
                            ->viewData([
                                'modules' => AdminPermissions::modules(),
                                'actions' => AdminPermissions::actions(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Forms\Get $get): bool => ! (bool) $get('is_super_admin')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\IconColumn::make('is_super_admin')
                    ->label('Super Admin')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('admins_count')
                    ->counts('admins')
                    ->label('Admins'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (AdminRole $record): bool => ! $record->is_super_admin),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminRoles::route('/'),
            'create' => Pages\CreateAdminRole::route('/create'),
            'edit' => Pages\EditAdminRole::route('/{record}/edit'),
        ];
    }
}
