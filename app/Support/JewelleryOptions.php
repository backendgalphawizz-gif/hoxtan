<?php

namespace App\Support;

class JewelleryOptions
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function purities(?string $metalType = null): array
    {
        $all = config('jewellery.purities', []);

        // Legacy flat list: [['value' => '22K', 'label' => '22K'], ...]
        if (array_is_list($all)) {
            return $all;
        }

        $key = in_array($metalType, ['gold', 'silver'], true) ? $metalType : 'default';

        return $all[$key] ?? $all['default'] ?? [];
    }

    /**
     * @return array<string, string>
     */
    public static function puritySelectOptions(?string $metalType = null): array
    {
        return collect(self::purities($metalType))
            ->mapWithKeys(fn (array $row) => [($row['value'] ?? '') => ($row['label'] ?? $row['value'] ?? '')])
            ->filter()
            ->all();
    }
}
