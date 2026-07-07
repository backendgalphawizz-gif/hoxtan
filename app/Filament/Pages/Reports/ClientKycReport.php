<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Exports\Reports\ClientKycReportExporter;
use App\Models\KycDetail;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ClientKycReport extends BaseReportPage
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_client_kyc';
    }

    protected static ?string $title = 'Client ID & KYC Bundle';

    public function table(Table $table): Table
    {
        return $table
            ->query(KycDetail::query()->with('user'))
            ->columns([
                TextColumn::make('user.id')->label('Client ID'),
                TextColumn::make('user.name')->searchable(),
                TextColumn::make('user.phone'),
                TextColumn::make('full_name'),
                TextColumn::make('pan_number'),
                TextColumn::make('aadhaar_number'),
                TextColumn::make('bank_name')->placeholder('—'),
                TextColumn::make('upi_id')->placeholder('—'),
                TextColumn::make('face_verification_status')->badge(),
                TextColumn::make('bank_verification_status')->badge(),
                TextColumn::make('pan_document')->label('PAN Doc')->limit(20),
                TextColumn::make('submitted_at')->dateTime('d M Y'),
            ])
            ->filters([
                SelectFilter::make('face_verification_status')->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ]),
            ])
            ->headerActions([
                static::reportExportAction(ClientKycReportExporter::class),
            ])
            ->defaultSort('submitted_at', 'desc');
    }
}
