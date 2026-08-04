<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Models\MetalRate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

abstract class MetalRateResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static ?string $model = MetalRate::class;

    protected static ?string $navigationGroup = 'Finance';

    protected static bool $shouldRegisterNavigation = false;

    abstract protected static function metalType(): string;

    abstract protected static function resolveNavigationLabel(): string;

    abstract protected static function resolveNavigationIcon(): string;

    public static function getNavigationLabel(): string
    {
        return static::resolveNavigationLabel();
    }

    public static function getNavigationIcon(): ?string
    {
        return static::resolveNavigationIcon();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('metal_type', static::metalType());
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('metal_type')
                    ->default(static::metalType()),
                Forms\Components\TextInput::make('rate_per_gram')
                    ->label('Rate per Gram (₹)')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->prefix('₹'),
                Forms\Components\Select::make('source')
                    ->options([
                        'live_sync' => 'Live Sync',
                        'manual_override' => 'Manual Override',
                    ])
                    ->required()
                    ->default('manual_override'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active Rate')
                    ->default(true),
                Forms\Components\Textarea::make('notes')
                    ->maxLength(500)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('rate_per_gram')
                    ->label('Rate/Gram')
                    ->inr()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('source')
                    ->colors(['info' => 'live_sync', 'warning' => 'manual_override']),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('updatedBy.name')->label('Updated By'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created at')
                    ->dateTime('d M Y, h:i A')
                    ->timezone('Asia/Kolkata')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
