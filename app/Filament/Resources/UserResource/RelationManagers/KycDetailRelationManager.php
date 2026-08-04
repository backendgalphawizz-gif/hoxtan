<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\KycDetail;
use App\Support\FilamentFormFields;
use App\Support\FilamentTableActions;
use App\Support\KycPayload;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class KycDetailRelationManager extends RelationManager
{
    protected static string $relationship = 'kycDetail';

    protected static ?string $title = 'KYC Details';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FilamentFormFields::fullName()
                    ->required(),
                FilamentFormFields::panNumber(),
                FilamentFormFields::aadhaarNumber(),
                Forms\Components\DatePicker::make('date_of_birth')
                    ->maxDate(now()->subYears(18))
                    ->native(false)
                    ->rules(['nullable', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()]),
                Forms\Components\Textarea::make('address')
                    ->maxLength(500)
                    ->columnSpanFull(),
                FilamentFormFields::city(),
                FilamentFormFields::state(),
                FilamentFormFields::pincode(),
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
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('pan_document')
                    ->image()
                    ->directory('kyc/pan'),
                Forms\Components\FileUpload::make('aadhaar_front')
                    ->image()
                    ->directory('kyc/aadhaar'),
                Forms\Components\FileUpload::make('aadhaar_back')
                    ->image()
                    ->directory('kyc/aadhaar'),
                Forms\Components\FileUpload::make('selfie_photo')
                    ->image()
                    ->directory('kyc/selfie')
                    ->label('Selfie / Face Photo'),
                Forms\Components\Select::make('face_verification_status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending')
                    ->nullable(),
                Forms\Components\Textarea::make('face_verification_notes')
                    ->label('Face Verification Notes')
                    ->maxLength(500),
                Forms\Components\Textarea::make('rejection_reason')
                    ->maxLength(500)
                    ->visible(fn (Forms\Get $get) => $get('face_verification_status') === 'rejected'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name'),
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
                    ]),
                Tables\Columns\TextColumn::make('bank_name')
                    ->label('Bank')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('account_number')
                    ->label('A/C No')
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('bank_verification_status')
                    ->label('Bank Status')
                    ->formatStateUsing(fn (?string $state): string => filled($state)
                        ? str($state)->replace('_', ' ')->title()
                        : '—')
                    ->colors([
                        'success' => fn (?string $state): bool => in_array($state, ['verified', 'approved'], true),
                        'warning' => 'pending',
                        'danger' => 'rejected',
                    ]),
                Tables\Columns\BadgeColumn::make('face_verification_status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ])
                    ->label('Face Verification'),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->dateTime('d M Y H:i'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data) {
                        $data['submitted_at'] = now();
                        $data['face_verification_status'] = $data['face_verification_status'] ?? 'pending';

                        return $data;
                    }),
            ])
            ->actions([
                FilamentTableActions::edit(),
                FilamentTableActions::make('approveKyc')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->tooltip('Approve KYC')
                    ->requiresConfirmation()
                    ->visible(fn (KycDetail $record): bool => KycPayload::requiresAdminKycApproval(
                        $record,
                        $record->user,
                    ))
                    ->action(function (KycDetail $record) {
                        $record->update([
                            'face_verification_status' => 'approved',
                            'reviewed_at' => now(),
                            'reviewed_by' => Auth::guard('admin')->id(),
                        ]);
                        $record->user->update(['kyc_status' => 'approved']);
                        Notification::make()->title('KYC Approved')->success()->send();
                    }),
                FilamentTableActions::make('rejectKyc')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->tooltip('Reject KYC')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->required()
                            ->maxLength(500),
                    ])
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
            ]);
    }
}
