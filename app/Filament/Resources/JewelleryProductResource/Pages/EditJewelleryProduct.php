<?php

namespace App\Filament\Resources\JewelleryProductResource\Pages;

use App\Filament\Resources\JewelleryProductResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Models\JewelleryProduct;
use App\Support\JewelleryPricing;
use Filament\Actions;

class EditJewelleryProduct extends BaseEditRecord
{
    protected static string $resource = JewelleryProductResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (! empty($data['has_size_variants'])) {
            return $data;
        }

        $pricing = JewelleryPricing::calculate(
            $data['metal_type'] ?? null,
            $data['weight_grams'] ?? null,
            $data['making_charge_percent'] ?? null,
            $data['discount_type'] ?? null,
            $data['discount_value'] ?? null,
        );

        $data['price'] = $pricing['total'];

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncVariants($this->record);
    }

    protected function syncVariants(JewelleryProduct $product): void
    {
        if (! $product->has_size_variants) {
            $product->variants()->delete();

            return;
        }

        $product->load('variants');

        foreach ($product->variants as $variant) {
            $variant->setRelation('product', $product);
            $variant->save();
        }

        $product->syncVariantDerivedFields();
    }
}
