<?php

namespace App\Filament\Resources\JewelleryProductResource\Pages;

use App\Filament\Resources\JewelleryProductResource;
use App\Filament\Resources\Pages\BaseCreateRecord;
use App\Models\JewelleryProduct;

class CreateJewelleryProduct extends BaseCreateRecord
{
    protected static string $resource = JewelleryProductResource::class;

    protected function afterCreate(): void
    {
        $this->syncVariants($this->record);
    }

    protected function syncVariants(JewelleryProduct $product): void
    {
        if (! $product->has_size_variants) {
            $product->variants()->delete();
            $product->refresh();

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
