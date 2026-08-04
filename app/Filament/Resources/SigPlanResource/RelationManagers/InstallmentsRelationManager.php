<?php

namespace App\Filament\Resources\SigPlanResource\RelationManagers;

use App\Services\SigPlanService;
use App\Support\FilamentTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class InstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'installments';

    protected static ?string $title = 'SIG Transactions';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->prefix('₹')
                    ->required(),
                Forms\Components\TextInput::make('quantity_grams')
                    ->label('Gold/Silver (g)')
                    ->numeric()
                    ->step(0.0001),
                Forms\Components\TextInput::make('rate_per_gram')
                    ->numeric()
                    ->prefix('₹'),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'success' => 'Success',
                        'failed' => 'Failed',
                    ])
                    ->required(),
                Forms\Components\DateTimePicker::make('scheduled_at')
                    ->required(),
                Forms\Components\Textarea::make('failure_reason')
                    ->visible(fn (Forms\Get $get) => $get('status') === 'failed'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference_id')
            ->columns([
                Tables\Columns\TextColumn::make('reference_id')->label('Txn ID')->searchable(),
                Tables\Columns\TextColumn::make('scheduled_at')->dateTime('d M Y, h:i A')->sortable(),
                Tables\Columns\TextColumn::make('amount')->inr(),
                Tables\Columns\TextColumn::make('quantity_grams')->grams(4)->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => config('sig.installment_statuses.'.$state, Str::headline((string) $state)))
                    ->colors([
                        'warning' => fn (?string $state): bool => in_array($state, ['pending', 'withdrawal_pending'], true),
                        'success' => fn (?string $state): bool => in_array($state, ['success', 'withdrawal'], true),
                        'danger' => fn (?string $state): bool => in_array($state, ['failed', 'withdrawal_rejected'], true),
                    ]),
                Tables\Columns\TextColumn::make('processed_at')->dateTime('d M Y, h:i A')->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add transaction')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = $this->getOwnerRecord()->user_id;

                        return $data;
                    })
                    ->after(function ($record): void {
                        app(SigPlanService::class)->syncStats($this->getOwnerRecord());
                        Notification::make()->title('Transaction added')->success()->send();
                    }),
            ])
            ->actions([
                FilamentTableActions::edit()
                    ->after(fn () => app(SigPlanService::class)->syncStats($this->getOwnerRecord())),
                FilamentTableActions::delete()
                    ->after(fn () => app(SigPlanService::class)->syncStats($this->getOwnerRecord())),
            ])
            ->defaultSort('scheduled_at', 'desc');
    }
}
