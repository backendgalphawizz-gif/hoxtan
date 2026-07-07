<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Exports\Reports\OldGoldBookingExporter;
use App\Models\OldGoldBooking;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OldGoldBookingReport extends BaseReportPage
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_old_gold';
    }

    protected static ?string $title = 'Old Gold Booking Report';

    public function table(Table $table): Table
    {
        return $table
            ->query(OldGoldBooking::query()->with(['user', 'payment']))
            ->columns([
                TextColumn::make('booking_number')->searchable(),
                TextColumn::make('user.name'),
                TextColumn::make('status')->badge(),
                TextColumn::make('estimated_weight_grams')->grams(3),
                TextColumn::make('quoted_amount')->inr(),
                TextColumn::make('final_amount')->inr(),
                TextColumn::make('payment.status')->label('Payment')->badge(),
                TextColumn::make('created_at')->dateTime('d M Y'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'processing' => 'Processing',
                    'completed' => 'Completed',
                    'failed' => 'Failed',
                    'cancelled' => 'Cancelled',
                ]),
            ])
            ->headerActions([static::reportExportAction(OldGoldBookingExporter::class)])
            ->emptyStateHeading('No old gold bookings yet');
    }
}
