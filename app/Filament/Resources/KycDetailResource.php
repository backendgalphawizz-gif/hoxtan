<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KycDetailResource\Pages;
use App\Models\KycDetail;
use App\Support\FilamentDateFilters;
use App\Support\FilamentTableActions;
use App\Support\NavigationBadgeCounts;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class KycDetailResource extends Resource
{
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
                        Forms\Components\TextInput::make('full_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('pan_number')
                            ->regex('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/')
                            ->maxLength(10),
                        Forms\Components\TextInput::make('aadhaar_number')
                            ->regex('/^\d{12}$/')
                            ->maxLength(12),
                        Forms\Components\DatePicker::make('date_of_birth')
                            ->native(false)
                            ->maxDate(now()->subYears(18))
                            ->rules(['nullable', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()]),
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
                        Forms\Components\Select::make('face_verification_status')
                            ->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'])
                            ->required(),
                        Forms\Components\Textarea::make('face_verification_notes')->maxLength(500),
                        Forms\Components\Textarea::make('rejection_reason')
                            ->required(fn (Forms\Get $get) => $get('face_verification_status') === 'rejected')
                            ->maxLength(500),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('full_name')->searchable(),
                Tables\Columns\TextColumn::make('pan_number'),
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
                Tables\Filters\SelectFilter::make('face_verification_status')
                    ->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']),
                FilamentDateFilters::tableFilter('submitted_between', 'submitted_at', 'Submitted Date'),
            ])
            ->actions([
                FilamentTableActions::view(),
                FilamentTableActions::edit(),
                FilamentTableActions::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->tooltip('Approve')
                    ->visible(fn (KycDetail $r) => $r->face_verification_status !== 'approved')
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
