<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Exports\Reports\JewelleryInventoryExporter;
use App\Models\JewelleryProduct;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JewelleryInventoryReport extends BaseReportPage
{
    protected static function adminPermissionModule(): string
    {
        return 'reports_jewellery_inventory';
    }

    protected static ?string $title = 'Jewellery Inventory Status';

    public function table(Table $table): Table
    {
        return $table
            ->query(JewelleryProduct::query())
            ->columns([
                TextColumn::make('sku')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('stock_status')->badge(),
                TextColumn::make('price')->inr(),
                TextColumn::make('weight_grams')->grams(3),
                TextColumn::make('metal_type')->badge(),
                TextColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('stock_status')->options([
                    'in_stock' => 'In Stock',
                    'out_of_stock' => 'Out of Stock',
                    'sold_out' => 'Sold Out',
                    'coming_soon' => 'Coming Soon',
                ]),
            ])
            ->headerActions([static::reportExportAction(JewelleryInventoryExporter::class)])
            ->emptyStateHeading('No jewellery products yet');
    }
}
