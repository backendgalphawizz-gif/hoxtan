<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Filament\Exports\KycDetailExporter;
use App\Filament\Resources\KycDetailResource\Pages;
use App\Models\KycDetail;
use App\Services\KycService;
use App\Support\FilamentDateFilters;
use App\Support\FilamentExportActions;
use App\Support\FilamentFormFields;
use App\Support\FilamentTableActions;
use App\Support\KycPayload;
use App\Support\NavigationBadgeCounts;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class KycDetailResource extends Resource
{
    use InteractsWithAdminPermissions;

    protected static function adminPermissionModule(): string
    {
        return 'kyc';
    }

    protected static ?string $model = KycDetail::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'View KYC Details';

    protected static ?string $modelLabel = 'KYC Detail';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->required()
                            ->disabled(fn (?KycDetail $record) => $record !== null),
                        FilamentFormFields::fullName()
                            ->required(),
                        FilamentFormFields::panNumber(),
                        FilamentFormFields::aadhaarNumber(),
                        Forms\Components\DatePicker::make('date_of_birth')
                            ->native(false)
                            ->maxDate(now()->subYears(18))
                            ->rules(['nullable', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()]),
                    ])->columns(2),

                Forms\Components\Section::make('Bank Details')
                    ->schema([
                        FilamentFormFields::fullName('account_holder_name', 'Account Holder Name', false, 32),
                        FilamentFormFields::name('bank_name', 'Bank Name', false, 100),
                        Forms\Components\TextInput::make('account_number')
                            ->label('A/C No')
                            ->maxLength(30)
                            ->regex('/^\d{9,18}$/')
                            ->validationMessages([
                                'regex' => 'Account number must be 9–18 digits.',
                            ]),
                        Forms\Components\TextInput::make('ifsc_code')
                            ->label('IFSC Code')
                            ->maxLength(11)
                            ->minLength(11)
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper($state) : null)
                            ->regex('/^[A-Z]{4}0[A-Z0-9]{6}$/')
                            ->validationMessages([
                                'regex' => 'Invalid IFSC code format.',
                            ]),
                    ])->columns(2),

                Forms\Components\Section::make('Documents')
                    ->schema([
                        Forms\Components\FileUpload::make('pan_document')->image()->directory('kyc/pan'),
                        Forms\Components\FileUpload::make('aadhaar_front')->image()->directory('kyc/aadhaar'),
                        Forms\Components\FileUpload::make('aadhaar_back')->image()->directory('kyc/aadhaar'),
                        Forms\Components\FileUpload::make('selfie_photo')->image()->directory('kyc/selfie')->label('Face Photo'),
                    ])->columns(2),

                Forms\Components\Section::make('Verification')
                    ->schema([
                        Forms\Components\Placeholder::make('pan_verification_status_display')
                            ->label('PAN Status')
                            ->content(fn (?KycDetail $record): string => filled($record?->pan_verification_status)
                                ? str($record->pan_verification_status)->replace('_', ' ')->title()
                                : '—'),
                        Forms\Components\Placeholder::make('pan_verified_at_display')
                            ->label('PAN Verified At')
                            ->content(fn (?KycDetail $record): string => $record?->pan_verified_at
                                ? $record->pan_verified_at->format('d M Y H:i')
                                : '—'),
                        Forms\Components\Select::make('face_verification_status')
                            ->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'])
                            ->default('pending')
                            ->nullable(),
                        Forms\Components\Textarea::make('face_verification_notes')->maxLength(500),
                        Forms\Components\Textarea::make('rejection_reason')
                            ->required(fn (Forms\Get $get) => $get('face_verification_status') === 'rejected')
                            ->maxLength(500),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('full_name')->searchable(),
                Tables\Columns\TextColumn::make('pan_number'),
                Tables\Columns\BadgeColumn::make('pan_verification_status')
                    ->label('PAN')
                    ->formatStateUsing(fn (?string $state): string => filled($state)
                        ? str($state)->replace('_', ' ')->title()
                        : '—')
                    ->colors([
                        'warning' => fn (?string $state): bool => in_array($state, ['action_required', 'pending', 'otp_sent'], true),
                        'success' => 'verified',
                        'danger' => 'rejected',
                        'info' => 'submitted',
                    ]),
                Tables\Columns\ImageColumn::make('selfie_photo')
                    ->label('Face')
                    ->disk('public')
                    ->circular()
                    ->size(40)
                    ->checkFileExistence(false),
                Tables\Columns\BadgeColumn::make('face_verification_status')
                    ->label('Face Status')
                    ->colors(['warning' => 'pending', 'success' => 'approved', 'danger' => 'rejected']),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime('d M Y')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('pan_verification_status')
                    ->label('PAN Status')
                    ->options([
                        'action_required' => 'Action Required',
                        'otp_sent' => 'OTP Sent',
                        'verified' => 'Verified',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\SelectFilter::make('face_verification_status')
                    ->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']),
                FilamentDateFilters::tableFilter('submitted_between', 'submitted_at', 'Submitted Date'),
            ])
            ->actions([
                FilamentTableActions::view(),
                FilamentTableActions::edit(),
                FilamentTableActions::make('verify_pan')
                    ->icon('heroicon-o-shield-check')
                    ->color('info')
                    ->tooltip('Verify PAN')
                    ->label('Verify PAN')
                    ->visible(fn (KycDetail $record): bool => filled($record->pan_number)
                        && $record->pan_verification_status !== 'verified')
                    ->requiresConfirmation()
                    ->modalHeading('Verify PAN with Surepass')
                    ->modalDescription(fn (KycDetail $record): string => 'Verify PAN '
                        .strtoupper((string) $record->pan_number)
                        .' for '.$record->user?->name.' via Surepass.')
                    ->action(function (KycDetail $record): void {
                        try {
                            $result = app(KycService::class)->applyPanVerification(
                                $record->user,
                                (string) $record->pan_number,
                            );

                            Notification::make()
                                ->title('PAN verified')
                                ->body((string) ($result['message'] ?? 'PAN verified successfully.'))
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('PAN verification failed')
                                ->body(collect($e->errors())->flatten()->first() ?: 'Verification failed.')
                                ->danger()
                                ->send();
                        }
                    }),
                FilamentTableActions::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->tooltip('Approve')
                    ->visible(fn (KycDetail $record): bool => KycPayload::requiresAdminKycApproval(
                        $record,
                        $record->user,
                    ) && $record->face_verification_status !== 'approved')
                    ->requiresConfirmation()
                    ->action(function (KycDetail $record) {
                        $record->update([
                            'face_verification_status' => 'approved',
                            'reviewed_at' => now(),
                            'reviewed_by' => Auth::guard('admin')->id(),
                        ]);
                        $record->user->update(['kyc_status' => 'approved']);
                        Notification::make()->title('KYC Approved')->success()->send();
                    }),
                FilamentTableActions::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->tooltip('Reject')
                    ->visible(fn (KycDetail $r) => $r->face_verification_status !== 'rejected')
                    ->form([Forms\Components\Textarea::make('rejection_reason')->required()])
                    ->action(function (KycDetail $record, array $data) {
                        $record->update([
                            'face_verification_status' => 'rejected',
                            'rejection_reason' => $data['rejection_reason'],
                            'reviewed_at' => now(),
                            'reviewed_by' => Auth::guard('admin')->id(),
                        ]);
                        $record->user->update(['kyc_status' => 'rejected']);
                        Notification::make()->title('KYC Rejected')->danger()->send();
                    }),
            ])
            ->bulkActions([
                FilamentExportActions::bulkExport(KycDetailExporter::class, 'kyc'),
            ])
            ->defaultSort('submitted_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKycDetails::route('/'),
            'create' => Pages\CreateKycDetail::route('/create'),
            'view' => Pages\ViewKycDetail::route('/{record}'),
            'edit' => Pages\EditKycDetail::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return NavigationBadgeCounts::format(NavigationBadgeCounts::pendingKycVerifications());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }
}
