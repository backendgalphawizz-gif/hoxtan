<?php

namespace App\Filament\Resources\JewelleryProductResource\Pages;

use App\Filament\Resources\JewelleryProductResource;
use App\Filament\Resources\Pages\BaseCreateRecord;
use App\Models\JewelleryProduct;

class CreateJewelleryProduct extends BaseCreateRecord
{
    protected static string $resource = JewelleryProductResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! empty($data['has_size_variants'])) {
            $data['price'] = $data['price'] ?? 0;
            $data['size'] = null;
            // Weight lives on variants; keep column non-null issues from popping up.
            if (! array_key_exists('weight_grams', $data) || $data['weight_grams'] === null || $data['weight_grams'] === '') {
                $data['weight_grams'] = null;
            }
        }

        return $data;
    }

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
