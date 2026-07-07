<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Exports\Reports\KycStatusReportExporter;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class KycStatusReport extends BaseReportPage
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_kyc_status';
    }

    protected static ?string $title = 'KYC Status Reports';

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->with('kycDetail'))
            ->columns([
                TextColumn::make('id')->label('Client ID'),
                TextColumn::make('name')->searchable(),
                TextColumn::make('phone'),
                TextColumn::make('kyc_status')->badge(),
                TextColumn::make('kycDetail.pan_number')->label('PAN')->placeholder('Pending'),
                TextColumn::make('kycDetail.aadhaar_number')->label('Aadhaar')->placeholder('Pending'),
                TextColumn::make('kycDetail.face_verification_status')->label('Face')->badge(),
                TextColumn::make('kycDetail.bank_verification_status')->label('Bank')->badge(),
                TextColumn::make('kycDetail.rejection_reason')->limit(30)->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('kyc_status')->options([
                    'pending' => 'Pending',
                    'submitted' => 'Submitted',
                    'verified' => 'Verified',
                    'rejected' => 'Rejected',
                ]),
            ])
            ->headerActions([
                static::reportExportAction(KycStatusReportExporter::class),
            ]);
    }
}
