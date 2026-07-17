<?php

namespace App\Support;

use App\Services\JewelleryKaratRateService;

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
            return self::filterByActiveKaratRates($all);
        }

        $key = in_array($metalType, ['gold', 'silver'], true) ? $metalType : 'default';

        return self::filterByActiveKaratRates($all[$key] ?? $all['default'] ?? []);
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

    /**
     * Hide 20K/18K/16K/14K unless that karat rate is active in admin.
     * 24K / 22K and silver purities are always shown.
     *
     * @param  list<array{value?: string, label?: string}>  $rows
     * @return list<array{value: string, label: string}>
     */
    protected static function filterByActiveKaratRates(array $rows): array
    {
        $karatRates = app(JewelleryKaratRateService::class);
        $managed = $karatRates->managedPurities();
        $active = $karatRates->activeManagedPurities();

        return collect($rows)
            ->filter(function (array $row) use ($managed, $active): bool {
                $value = (string) ($row['value'] ?? '');

                if ($value === '') {
                    return false;
                }

                if (! in_array($value, $managed, true)) {
                    return true;
                }

                return in_array($value, $active, true);
            })
            ->values()
            ->all();
    }
}
