<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Resources\JewelleryEmiRefundRequestResource\Pages;
use App\Models\JewelleryEmiRefundRequest;
use App\Services\JewelleryEmiCancellationService;
use App\Support\FilamentTableActions;
use App\Support\NavigationBadgeCounts;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class JewelleryEmiRefundRequestResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static function adminPermissionModule(): string
    {
        return 'jewellery_emi_refunds';
    }

    protected static ?string $model = JewelleryEmiRefundRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Jewellery Management';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'EMI Refund Requests';

    protected static ?string $modelLabel = 'EMI Refund Request';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'order', 'reviewer']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Request')
                    ->schema([
                        Forms\Components\TextInput::make('reference_id')->disabled(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'auto_approved' => 'Auto Approved',
                                'rejected' => 'Rejected',
                                'refunded' => 'Refunded',
                            ])
                            ->disabled(),
                        Forms\Components\TextInput::make('order.order_number')
                            ->label('Order')
                            ->disabled(),
                        Forms\Components\TextInput::make('user.name')
                            ->label('Customer')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('requested_at')->disabled()->native(false),
                        Forms\Components\DateTimePicker::make('auto_approve_at')
                            ->label('Auto Approve At')
                            ->disabled()
                            ->native(false),
                    ])->columns(2),
                Forms\Components\Section::make('Refund Calculation')
                    ->schema([
                        Forms\Components\TextInput::make('paid_amount')->prefix('₹')->disabled(),
                        Forms\Components\TextInput::make('cancellation_fee_amount')
                            ->label('Cancellation Fee (10%)')
                            ->prefix('₹')
                            ->disabled(),
                        Forms\Components\TextInput::make('gst_amount')
                            ->label('GST on Fee (3%)')
                            ->prefix('₹')
                            ->disabled(),
                        Forms\Components\TextInput::make('deduction_amount')
                            ->label('Total Deduction')
                            ->prefix('₹')
                            ->disabled(),
                        Forms\Components\TextInput::make('refund_amount')
                            ->label('Refund Amount')
                            ->prefix('₹')
                            ->disabled(),
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->disabled()
                            ->columnSpanFull(),
                    ])->columns(2),
                Forms\Components\Section::make('Bank Account')
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')->disabled(),
                        Forms\Components\TextInput::make('account_holder_name')->disabled(),
                        Forms\Components\TextInput::make('account_number')->disabled(),
                        Forms\Components\TextInput::make('ifsc_code')->disabled(),
                        Forms\Components\TextInput::make('refund_reference')
                            ->label('Refund UTR / Reference')
                            ->disabled(fn (?JewelleryEmiRefundRequest $record): bool => ! $record?->isPending()),
                        Forms\Components\Textarea::make('rejection_reason')
                            ->disabled(fn (?JewelleryEmiRefundRequest $record): bool => ! $record?->isPending())
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_id')
                    ->label('Request ID')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->formatStateUsing(fn (?string $state): string => $state ? '#'.$state : '—'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('paid_amount')->inr()->label('Paid EMI'),
                Tables\Columns\TextColumn::make('deduction_amount')->inr()->label('Deduction'),
                Tables\Columns\TextColumn::make('refund_amount')->inr()->label('Refund')->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => fn ($state) => in_array($state, ['approved', 'auto_approved', 'refunded'], true),
                        'danger' => 'rejected',
                    ])
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state))),
                Tables\Columns\TextColumn::make('auto_approve_at')
                    ->label('Auto Approve')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('requested_at')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->defaultSort('requested_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'refunded' => 'Refunded',
                        'auto_approved' => 'Auto Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                FilamentTableActions::view(),
                FilamentTableActions::make('approve')
                    ->label('Approve Refund')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (JewelleryEmiRefundRequest $record): bool => $record->isPending())
                    ->form([
                        Forms\Components\TextInput::make('refund_reference')
                            ->label('Bank UTR / Reference (optional)')
                            ->maxLength(100),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Approve EMI refund')
                    ->modalDescription('Refund will be marked as paid to the customer bank account.')
                    ->action(function (JewelleryEmiRefundRequest $record, array $data): void {
                        app(JewelleryEmiCancellationService::class)->approve(
                            $record,
                            Auth::guard('admin')->id(),
                            $data['refund_reference'] ?? null,
                        );

                        Notification::make()
                            ->title('Refund approved')
                            ->success()
                            ->send();
                    }),
                FilamentTableActions::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (JewelleryEmiRefundRequest $record): bool => $record->isPending())
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->requiresConfirmation()
                    ->action(function (JewelleryEmiRefundRequest $record, array $data): void {
                        app(JewelleryEmiCancellationService::class)->reject(
                            $record,
                            (int) Auth::guard('admin')->id(),
                            $data['rejection_reason'],
                        );

                        Notification::make()
                            ->title('Refund request rejected')
                            ->warning()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJewelleryEmiRefundRequests::route('/'),
            'view' => Pages\ViewJewelleryEmiRefundRequest::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return NavigationBadgeCounts::format(NavigationBadgeCounts::pendingJewelleryEmiRefunds());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
