<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Exports\Reports\JewelleryActivityExporter;
use App\Models\JewelleryCartItem;
use App\Models\JewelleryOrder;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JewelleryActivityReport extends BaseReportPage
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_jewellery';
    }

    protected static ?string $title = 'Jewellery Activity';

    public string $viewType = 'orders';

    public function mount(): void
    {
        $tab = request()->query('tab');

        if (in_array($tab, ['orders', 'cart'], true)) {
            $this->viewType = $tab;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ordersTab')
                ->label('Orders & Payments')
                ->color($this->viewType === 'orders' ? 'primary' : 'gray')
                ->url(static::getUrl(['tab' => 'orders'])),
            Action::make('cartTab')
                ->label('Cart Items')
                ->color($this->viewType === 'cart' ? 'primary' : 'gray')
                ->url(static::getUrl(['tab' => 'cart'])),
        ];
    }

    public function table(Table $table): Table
    {
        if ($this->viewType === 'cart') {
            return $table
                ->query(JewelleryCartItem::query()->with(['user', 'product']))
                ->columns([
                    TextColumn::make('user.name')->searchable(),
                    TextColumn::make('user.phone'),
                    TextColumn::make('product.name')->label('Product'),
                    TextColumn::make('product.sku'),
                    TextColumn::make('quantity')->badge(),
                    TextColumn::make('created_at')->dateTime('d M Y'),
                ])
                ->headerActions([static::reportExportAction(JewelleryActivityExporter::class)])
                ->emptyStateHeading('No cart items yet');
        }

        return $table
            ->query(JewelleryOrder::query()->with(['user', 'payment', 'invoice']))
            ->columns([
                TextColumn::make('order_number')->searchable(),
                TextColumn::make('user.name')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('payment.status')->label('Payment')->badge(),
                TextColumn::make('invoice.invoice_number')->label('Invoice')->placeholder('—')->searchable(),
                TextColumn::make('total_amount')->inr(),
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
            ->headerActions([static::reportExportAction(JewelleryActivityExporter::class)])
            ->emptyStateHeading('No jewellery orders yet')
            ->emptyStateDescription('Orders will appear here once the jewellery module is in use.');
    }
}
