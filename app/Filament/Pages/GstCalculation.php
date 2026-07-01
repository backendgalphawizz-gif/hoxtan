<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\InteractsWithAdminPermissions;
use App\Models\GstRecord;
use App\Services\GstService;
use App\Support\FilamentDateFilters;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class GstCalculation extends Page implements HasForms, HasTable
{
    use InteractsWithAdminPermissions;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static function adminPermissionModule(): string
    {
        return 'gst_calculation';
    }

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = null;

    protected static ?string $navigationLabel = 'Per Day GST Calculation';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'admin.gst.calculation';

    public function getSubheading(): ?string
    {
        return 'Calculate and review GST records for each day.';
    }

    public ?string $date = null;

    public ?array $gstResult = null;

    public function mount(): void
    {
        $this->date = now()->toDateString();
        $this->form->fill(['date' => $this->date]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Calculate GST for Date')
                    ->description('Compute CGST, SGST and total GST from completed transactions for a specific day.')
                    ->schema([
                        FilamentDateFilters::singleDateField('date', 'Report Date'),
                    ])
                    ->columns(1),
            ]);
    }

    public function calculateGst(): void
    {
        $data = $this->form->getState();
        $date = \Carbon\Carbon::parse($data['date']);

        if ($date->isFuture()) {
            $this->addError('data.date', 'Report date cannot be in the future.');

            return;
        }

        $record = app(GstService::class)->calculateForDate($date);

        $this->gstResult = [
            'taxable' => $record->total_taxable_amount,
            'cgst' => $record->cgst_amount,
            'sgst' => $record->sgst_amount,
            'total_gst' => $record->total_gst,
            'transactions' => $record->transaction_count,
        ];

        Notification::make()
            ->title('GST calculated for '.$date->format('d M Y'))
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(GstRecord::query()->latest('report_date'))
            ->columns([
                TextColumn::make('report_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('total_taxable_amount')
                    ->label('Taxable Amount')
                    ->inr()
                    ->sortable(),
                TextColumn::make('cgst_amount')
                    ->label('CGST')
                    ->inr(),
                TextColumn::make('sgst_amount')
                    ->label('SGST')
                    ->inr(),
                TextColumn::make('total_gst')
                    ->label('Total GST')
                    ->inr()
                    ->weight('bold')
                    ->color('success'),
                TextColumn::make('transaction_count')
                    ->label('Transactions')
                    ->badge(),
            ])
            ->defaultSort('report_date', 'desc')
            ->filters([
                FilamentDateFilters::tableFilter('report_date', 'report_date', 'Report Date'),
            ])
            ->paginated([10, 25, 50]);
    }
}
